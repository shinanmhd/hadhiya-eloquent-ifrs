<?php

namespace IFRS\Context;

use IFRS\Models\Entity;
use Illuminate\Support\Facades\Auth;

class AuthEntityResolver implements EntityResolver
{
    public function resolve(): ?Entity
    {
        $user = Auth::user();

        if ($user === null) {
            return null;
        }

        return $user->entity;
    }
}
