<?php

declare(strict_types=1);

namespace App\IdentityAccess\Domain\ValueObject;

use App\IdentityAccess\Domain\IdentityException;

final readonly class PasswordHash
{
    public function __construct(public string $value)
    {
        if ('' === trim($value) || strlen($value) > 255) {
            throw IdentityException::invalid('A valid password hash is required.');
        }
    }

    /** @return array<string, string> keeps the hash out of var_dump and logs */
    public function __debugInfo(): array
    {
        return ['value' => '[redacted]'];
    }
}
