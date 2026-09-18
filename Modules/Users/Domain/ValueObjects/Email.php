<?php

namespace Modules\Users\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Framework-agnostic value object. No Eloquent, no framework dependencies.
 */
final class Email
{
    private string $value;

    public function __construct(string $value)
    {
        if (! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email address: {$value}");
        }

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(Email $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
