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
        $message = 'Validation failed: ' . $this->formatErrors($errors);
        parent::__construct($message, $code);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Format errors as a human-readable string instead of raw JSON.
     */
    private function formatErrors(array $errors): string
    {
        $messages = [];
        foreach ($errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = "{$field}: {$error}";
            }
        }
        return implode('; ', $messages);
    }
}