<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\ValueObject;

use InvalidArgumentException;

final readonly class Username
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        $value = mb_strtolower(trim($value));
        if ($value === '' || mb_strlen($value) > 255) {
            throw new InvalidArgumentException('Username must contain between 1 and 255 characters.');
        }

        return new self($value);
    }

    public function value(): string { return $this->value; }

    public function equals(self $other): bool { return $this->value === $other->value; }
}
