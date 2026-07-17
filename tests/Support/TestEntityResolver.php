<?php

namespace IFRS\Tests\Support;

use IFRS\Context\EntityResolver;
use IFRS\Models\Entity;

class TestEntityResolver implements EntityResolver
{
    private static ?Entity $entity = null;

    public function resolve(): ?Entity
    {
        return self::$entity;
    }

    public static function setEntity(?Entity $entity): void
    {
        self::$entity = $entity;
    }
}
