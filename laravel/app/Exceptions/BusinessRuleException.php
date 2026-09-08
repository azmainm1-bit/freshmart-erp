<?php

namespace App\Exceptions;

use Exception;

/**
 * Base class for domain/business-rule failures that need a structured,
 * machine-readable response (error code + details), as opposed to a
 * generic validation error. Rendered as JSON by the handler registered in
 * bootstrap/app.php.
 */
class BusinessRuleException extends Exception
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = []
    ) {
        parent::__construct($message);
    }

    public function toResponseArray(): array
    {
        return [
            'error' => $this->errorCode,
            'message' => $this->getMessage(),
            'details' => $this->details,
        ];
    }
}
