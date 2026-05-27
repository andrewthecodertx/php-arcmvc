<?php

declare(strict_types=1);

namespace Arc\Validation;

class Validator
{
    private array $errors = [];
    private array $data;

    public function __construct(array $data, private array $rules, private array $messages = [])
    {
        $this->data = $data;
        $this->validate();
    }

    public static function make(array $data, array $rules, array $messages = []): self
    {
        return new self($data, $rules, $messages);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        if ($this->fails()) {
            throw new ValidationException($this->errors);
        }

        $validated = [];
        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->data)) {
                $validated[$field] = $this->data[$field];
            }
        }
        return $validated;
    }

    private function validate(): void
    {
        foreach ($this->rules as $field => $ruleSet) {
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);

            foreach ($rules as $rule) {
                $this->applyRule($field, $rule);
            }
        }
    }

    private function applyRule(string $field, string $rule): void
    {
        $value = $this->data[$field] ?? null;

        [$ruleName, $parameter] = $this->parseRule($rule);

        if ($this->isValid($ruleName, $value, $parameter)) {
            return;
        }

        $this->errors[$field][] = $this->errorMessage($field, $ruleName, $parameter);
    }

    private function parseRule(string $rule): array
    {
        if (str_contains($rule, ':')) {
            return explode(':', $rule, 2);
        }
        return [$rule, null];
    }

    private function isValid(string $rule, mixed $value, ?string $parameter): bool
    {
        return match ($rule) {
            'required' => $value !== null && $value !== '',
            'string' => is_string($value),
            'integer', 'int' => filter_var($value, FILTER_VALIDATE_INT) !== false,
            'numeric' => is_numeric($value),
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            'boolean', 'bool' => is_bool($value) || in_array($value, [0, 1, '0', '1'], true),
            'min' => $this->checkMin($value, (int) $parameter),
            'max' => $this->checkMax($value, (int) $parameter),
            'between' => $this->checkBetween($value, $parameter),
            'same' => $value === ($this->data[$parameter] ?? null),
            'different' => $value !== ($this->data[$parameter] ?? null),
            'in' => in_array($value, explode(',', $parameter), true),
            'not_in' => !in_array($value, explode(',', $parameter), true),
            'alpha' => is_string($value) && preg_match('/^[a-zA-Z]+$/', $value),
            'alpha_num' => is_string($value) && preg_match('/^[a-zA-Z0-9]+$/', $value),
            'regex' => is_string($value) && preg_match('/' . $parameter . '/', $value),
            'date' => strtotime($value) !== false,
            default => true,
        };
    }

    private function checkMin(mixed $value, int $min): bool
    {
        if (is_string($value)) {
            return strlen($value) >= $min;
        }
        if (is_numeric($value)) {
            return $value >= $min;
        }
        if (is_array($value)) {
            return count($value) >= $min;
        }
        return false;
    }

    private function checkMax(mixed $value, int $max): bool
    {
        if (is_string($value)) {
            return strlen($value) <= $max;
        }
        if (is_numeric($value)) {
            return $value <= $max;
        }
        if (is_array($value)) {
            return count($value) <= $max;
        }
        return false;
    }

    private function checkBetween(mixed $value, ?string $parameter): bool
    {
        if ($parameter === null) {
            return true;
        }
        [$min, $max] = array_map('intval', explode(',', $parameter));
        if (is_string($value)) {
            return strlen($value) >= $min && strlen($value) <= $max;
        }
        if (is_numeric($value)) {
            return $value >= $min && $value <= $max;
        }
        return false;
    }

    private function errorMessage(string $field, string $rule, ?string $parameter): string
    {
        $key = "{$field}.{$rule}";
        if (isset($this->messages[$key])) {
            return $this->messages[$key];
        }

        $displayField = ucfirst(str_replace('_', ' ', $field));

        return match ($rule) {
            'required' => "{$displayField} is required",
            'string' => "{$displayField} must be a string",
            'integer', 'int' => "{$displayField} must be an integer",
            'numeric' => "{$displayField} must be numeric",
            'email' => "{$displayField} must be a valid email address",
            'url' => "{$displayField} must be a valid URL",
            'boolean', 'bool' => "{$displayField} must be true or false",
            'min' => "{$displayField} must be at least {$parameter}",
            'max' => "{$displayField} must not exceed {$parameter}",
            'between' => "{$displayField} must be between {$parameter}",
            'same' => "{$displayField} must match {$parameter}",
            'different' => "{$displayField} must be different from {$parameter}",
            'in' => "{$displayField} is not a valid option",
            'not_in' => "{$displayField} is not allowed",
            'alpha' => "{$displayField} must only contain letters",
            'alpha_num' => "{$displayField} must only contain letters and numbers",
            'regex' => "{$displayField} format is invalid",
            'date' => "{$displayField} must be a valid date",
            default => "{$displayField} is invalid",
        };
    }
}