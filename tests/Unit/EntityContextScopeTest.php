<?php

namespace IFRS\Tests\Unit;

use IFRS\Context\EntityContext;
use IFRS\Context\AuthEntityResolver;
use IFRS\Context\NullEntityResolver;
use IFRS\Exceptions\MissingEntityContext;
use IFRS\Exceptions\EntityContextMismatch;
use IFRS\Models\Account;
use IFRS\Models\Currency;
use IFRS\Models\Entity;
use IFRS\Tests\TestCase;
use Illuminate\Support\Facades\Auth;

class EntityContextScopeTest extends TestCase
{
    public function testAuthenticatedUserDoesNotBypassStrictMode(): void
    {
        config()->set('ifrs.entity_context.resolver', NullEntityResolver::class);
        $this->app->forgetScopedInstances();

        $this->expectException(MissingEntityContext::class);

        Account::query()->count();
    }

    public function testAuthCompatibilityResolverIsOptInAtModelBoundary(): void
    {
        $entity = Auth::user()->entity;
        factory(Account::class)->create([
            'category_id' => null,
        ]);
        config()->set('ifrs.entity_context.resolver', AuthEntityResolver::class);
        $this->app->forgetScopedInstances();

        $this->assertSame(
            [$entity->id],
            Account::query()->distinct()->pluck('entity_id')->all()
        );
    }

    public function testExplicitContextOverridesAuthCompatibilityResolver(): void
    {
        $authenticatedEntity = Auth::user()->entity;
        $explicitEntity = $this->createEntityWithCurrency();
        config()->set('ifrs.entity_context.resolver', AuthEntityResolver::class);
        $this->app->forgetScopedInstances();

        $context = $this->app->make(EntityContext::class);

        $this->assertSame($authenticatedEntity->id, $context->id());
        $context->runForEntity(
            $explicitEntity,
            fn () => $this->assertSame($explicitEntity->id, $context->id())
        );
        $this->assertSame($authenticatedEntity->id, $context->id());
    }

    public function testEntityBoundQueriesFailClosedWithoutContext(): void
    {
        Auth::logout();
        config()->set('ifrs.entity_context.resolver', NullEntityResolver::class);
        $this->app->forgetScopedInstances();

        $this->assertNull($this->app->make(EntityContext::class)->current());

        $this->expectException(MissingEntityContext::class);

        Account::query()->count();
    }

    public function testExplicitContextIsolatesQueriesBetweenEntities(): void
    {
        $first = Auth::user()->entity;
        $second = $this->createEntityWithCurrency();

        $context = $this->app->make(EntityContext::class);

        $context->runForEntity($first, function () use ($first) {
            factory(Account::class)->create([
                'entity_id' => $first->id,
                'currency_id' => $first->currency_id,
            ]);
        });
        $context->runForEntity($second, function () use ($second) {
            factory(Account::class)->create([
                'entity_id' => $second->id,
                'currency_id' => $second->currency_id,
            ]);
        });

        $context->runForEntity($first, function () use ($first) {
            $this->assertGreaterThanOrEqual(1, Account::query()->count());
            $this->assertSame(
                [$first->id],
                Account::query()->distinct()->pluck('entity_id')->all()
            );
        });
        $context->runForEntity($second, function () use ($second) {
            $this->assertSame(1, Account::query()->count());
            $this->assertSame(
                [$second->id],
                Account::query()->distinct()->pluck('entity_id')->all()
            );
        });
    }

    public function testActiveContextAssignsEntityDuringCreation(): void
    {
        $second = $this->createEntityWithCurrency();
        $currency = $second->currency;

        $account = $this->app->make(EntityContext::class)->runForEntity(
            $second,
            fn () => factory(Account::class)->create([
                'entity_id' => null,
                'currency_id' => $currency->id,
            ])
        );

        $this->assertSame($second->id, $account->entity_id);
    }

    public function testConflictingEntityIdIsRejectedBeforeCreation(): void
    {
        $first = Auth::user()->entity;
        $second = $this->createEntityWithCurrency();
        $currency = $second->currency;

        $this->expectException(EntityContextMismatch::class);

        $this->app->make(EntityContext::class)->runForEntity(
            $second,
            fn () => factory(Account::class)->create([
                'entity_id' => $first->id,
                'currency_id' => $currency->id,
            ])
        );
    }

    private function createEntityWithCurrency(): Entity
    {
        $entity = factory(Entity::class)->create();

        $currency = $this->app->make(EntityContext::class)->runForEntity(
            $entity,
            fn () => factory(Currency::class)->create([
                'entity_id' => $entity->id,
            ])
        );
        $entity->currency_id = $currency->id;
        $entity->save();
        $entity->setRelation('currency', $currency);

        return $entity;
    }
}
