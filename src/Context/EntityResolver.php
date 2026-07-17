<?php

namespace IFRS\Context;

use IFRS\Models\Entity;

interface EntityResolver
{
    public function resolve(): ?Entity;
}
