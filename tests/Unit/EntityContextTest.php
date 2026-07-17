<?php

namespace IFRS\Tests\Unit;

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
}
