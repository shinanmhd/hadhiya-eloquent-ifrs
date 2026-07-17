<?php

namespace IFRS\Exceptions;

class EntityContextMismatch extends IFRSException
{
    public function __construct(string $message = null, int $code = null)
    {
        parent::__construct(
            $message ?? 'The model entity does not match the active accounting entity context.',
            $code
        );
    }
}
