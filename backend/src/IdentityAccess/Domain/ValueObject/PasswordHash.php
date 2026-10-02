<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\ValueObject;

use InvalidArgumentException;

final readonly class PasswordHash
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        if ($value === '') {
            throw new InvalidArgumentException('Password hash cannot be empty.');
        }
        return new self($value);
    }

    public function value(): string { return $this->value; }
}
