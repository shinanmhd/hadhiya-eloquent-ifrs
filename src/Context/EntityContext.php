<?php

namespace IFRS\Context;

use IFRS\Models\Entity;

interface EntityContext
{
    public function current(): ?Entity;

    public function id(): ?int;

    public function requireEntity(): Entity;

    public function runForEntity(Entity $entity, callable $callback): mixed;
}
