<?php

namespace IFRS\Context;

use IFRS\Exceptions\MissingEntityContext;
use IFRS\Models\Entity;

class StackEntityContext implements EntityContext
{
    /**
     * @var list<Entity>
     */
    private array $stack = [];

    public function __construct(
        private readonly EntityResolver $resolver
    ) {}

    public function current(): ?Entity
    {
        if ($this->stack !== []) {
            return $this->stack[array_key_last($this->stack)];
        }

        return $this->resolver->resolve();
    }

    public function id(): ?int
    {
        $id = $this->current()?->getKey();

        return $id === null ? null : (int) $id;
    }

    public function requireEntity(): Entity
    {
        return $this->current() ?? throw new MissingEntityContext();
    }

    public function runForEntity(Entity $entity, callable $callback): mixed
    {
        $this->stack[] = $entity;

        try {
            return $callback($entity);
        } finally {
            array_pop($this->stack);
        }
    }
}
