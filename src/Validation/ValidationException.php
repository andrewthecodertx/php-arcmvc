<?php

declare(strict_types=1);

namespace Arc\Validation;

use Exception;

class ValidationException extends Exception
{
    public function __construct(
        private array $errors,
        int $code = 422,
    ) {
        $message = 'Validation failed: ' . json_encode($errors, JSON_THROW_ON_ERROR);
        parent::__construct($message, $code);
    }

    public function errors(): array
    {
        return $this->errors;
    }
}