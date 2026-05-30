<?php

declare(strict_types=1);

namespace RoyaltyFusion\DianPhp\Validator;

final class ValidationResult
{
    /** @var ValidationError[] */
    private array $errors = [];

    public function addError(ValidationError $error): self
    {
        $this->errors[] = $error;
        return $this;
    }

    public function add(string $code, string $message, string $xpath = '', string $severity = 'error'): self
    {
        return $this->addError(new ValidationError($code, $message, $xpath, $severity));
    }

    public function merge(ValidationResult $other): self
    {
        foreach ($other->errors as $error) {
            $this->errors[] = $error;
        }
        return $this;
    }

    /** @return ValidationError[] */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function isValid(): bool
    {
        foreach ($this->errors as $error) {
            if ($error->severity === 'error') {
                return false;
            }
        }
        return true;
    }

    /** @return string[] */
    public function messages(): array
    {
        return array_map(fn (ValidationError $e) => "[{$e->code}] {$e->message}", $this->errors);
    }
}
