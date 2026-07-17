<?php

namespace IFRS\Tests\Unit;

use IFRS\Context\EntityContext;
use IFRS\Exceptions\MissingEntityContext;
use IFRS\Models\Account;
use IFRS\Models\Currency;
use IFRS\Models\Entity;
use IFRS\Tests\TestCase;
use Illuminate\Support\Facades\Auth;

class EntityContextScopeTest extends TestCase
{
    public function testEntityBoundQueriesFailClosedWithoutContext(): void
    {
        Auth::logout();
        $this->app->forgetScopedInstances();

        $this->assertNull($this->app->make(EntityContext::class)->current());

        $this->expectException(MissingEntityContext::class);

        Account::query()->count();
    }

    public function testExplicitContextIsolatesQueriesBetweenEntities(): void
    {
        $first = Auth::user()->entity;
        $second = factory(Entity::class)->create();
        $secondCurrency = factory(Currency::class)->create([
            'entity_id' => $second->id,
        ]);
        $second->currency_id = $secondCurrency->id;
        $second->save();

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
}
