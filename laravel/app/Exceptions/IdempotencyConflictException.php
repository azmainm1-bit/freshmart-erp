<?php

namespace App\Exceptions;

class IdempotencyConflictException extends BusinessRuleException
{
    public function __construct(string $message)
    {
        parent::__construct(409, 'IDEMPOTENCY_CONFLICT', $message);
    }
}
