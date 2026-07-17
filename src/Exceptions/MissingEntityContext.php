<?php

namespace IFRS\Exceptions;

class MissingEntityContext extends IFRSException
{
    public function __construct(string $message = null, int $code = null)
    {
        parent::__construct(
            $message ?? 'No accounting entity context is active.',
            $code
        );
    }
}
