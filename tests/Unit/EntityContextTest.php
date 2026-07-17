<?php

namespace IFRS\Tests\Unit;

use IFRS\Context\EntityResolver;
use IFRS\Exceptions\MissingEntityContext;
use IFRS\Models\Entity;
use IFRS\Tests\TestCase;

class EntityContextTest extends TestCase
{
    public function testEntityContextContractExposesRequiredOperations(): void
    {
        $this->assertTrue(
            interface_exists(\IFRS\Context\EntityContext::class),
            'The EntityContext contract must exist.'
        );

        $reflection = new \ReflectionClass(\IFRS\Context\EntityContext::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('current'));
        $this->assertTrue($reflection->hasMethod('id'));
        $this->assertTrue($reflection->hasMethod('requireEntity'));
        $this->assertTrue($reflection->hasMethod('runForEntity'));
    }

    public function testEntityResolverContractExposesResolveOperation(): void
    {
        $this->assertTrue(
            interface_exists(\IFRS\Context\EntityResolver::class),
            'The EntityResolver contract must exist.'
        );

        $reflection = new \ReflectionClass(\IFRS\Context\EntityResolver::class);

        $this->assertTrue($reflection->isInterface());
        $this->assertTrue($reflection->hasMethod('resolve'));
    }

    public function testContextExceptionsAreTypedIfrsExceptions(): void
    {
        $this->assertTrue(
            is_subclass_of(
                \IFRS\Exceptions\MissingEntityContext::class,
                \IFRS\Exceptions\IFRSException::class,
                true
            )
        );
        $this->assertTrue(
            is_subclass_of(
                \IFRS\Exceptions\EntityContextMismatch::class,
                \IFRS\Exceptions\IFRSException::class,
                true
            )
        );
    }

    public function testStrictContextIsEmptyAndRequireEntityFails(): void
    {
        $context = new \IFRS\Context\StackEntityContext(
            new \IFRS\Context\NullEntityResolver()
        );

        $this->assertNull($context->current());
        $this->assertNull($context->id());

        $this->expectException(MissingEntityContext::class);

        $context->requireEntity();
    }

    public function testRunForEntityExposesAndThenRestoresContext(): void
    {
        $entity = $this->entityWithId(11);
        $context = new \IFRS\Context\StackEntityContext(
            new \IFRS\Context\NullEntityResolver()
        );

        $result = $context->runForEntity($entity, function (Entity $active) use ($context) {
            $this->assertSame($active, $context->current());
            $this->assertSame(11, $context->id());

            return 'complete';
        });

        $this->assertSame('complete', $result);
        $this->assertNull($context->current());
    }

    public function testNestedContextRestoresItsParent(): void
    {
        $outer = $this->entityWithId(21);
        $inner = $this->entityWithId(22);
        $context = new \IFRS\Context\StackEntityContext(
            new \IFRS\Context\NullEntityResolver()
        );

        $context->runForEntity($outer, function () use ($context, $inner, $outer) {
            $this->assertSame($outer, $context->current());

            $context->runForEntity($inner, function () use ($context, $inner) {
                $this->assertSame($inner, $context->current());
            });

            $this->assertSame($outer, $context->current());
        });

        $this->assertNull($context->current());
    }

    public function testCallbackExceptionRestoresPreviousContext(): void
    {
        $outer = $this->entityWithId(31);
        $inner = $this->entityWithId(32);
        $context = new \IFRS\Context\StackEntityContext(
            new \IFRS\Context\NullEntityResolver()
        );

        $context->runForEntity($outer, function () use ($context, $inner, $outer) {
            try {
                $context->runForEntity($inner, static function () {
                    throw new \RuntimeException('posting failed');
                });
            } catch (\RuntimeException $exception) {
                $this->assertSame('posting failed', $exception->getMessage());
            }

            $this->assertSame($outer, $context->current());
        });

        $this->assertNull($context->current());
    }

    public function testFallbackResolverIsUsedOnlyWhenExplicitStackIsEmpty(): void
    {
        $fallback = $this->entityWithId(41);
        $explicit = $this->entityWithId(42);
        $resolver = new class($fallback) implements EntityResolver {
            public function __construct(private readonly Entity $entity) {}

            public function resolve(): ?Entity
            {
                return $this->entity;
            }
        };
        $context = new \IFRS\Context\StackEntityContext($resolver);

        $this->assertSame($fallback, $context->current());

        $context->runForEntity($explicit, function () use ($context, $explicit) {
            $this->assertSame($explicit, $context->current());
        });

        $this->assertSame($fallback, $context->current());
    }

    public function testAuthResolverReturnsAuthenticatedUsersEntity(): void
    {
        $resolver = new \IFRS\Context\AuthEntityResolver();

        $this->assertSame(\Auth::user()->entity->id, $resolver->resolve()?->id);
    }

    private function entityWithId(int $id): Entity
    {
        $entity = new Entity();
        $entity->id = $id;
        $entity->exists = true;

        return $entity;
    }
}
