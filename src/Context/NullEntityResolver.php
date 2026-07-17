<?php

namespace IFRS\Context;

use IFRS\Models\Entity;

class NullEntityResolver implements EntityResolver
{
    public function resolve(): ?Entity
    {
        return null;
    }
}
