<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\Timestamps;
use App\Volunteering\Domain\ValidityWindow;

final class ValidityWindows
{
    private function __construct()
    {
    }

    /** @param array<string, mixed> $row with valid_from, valid_until and revoked_at */
    public static function fromRow(array $row): ValidityWindow
    {
        return new ValidityWindow(
            Timestamps::fromDatabase((string) $row['valid_from']),
            Timestamps::fromDatabase((string) $row['valid_until']),
            null === $row['revoked_at'] ? null : Timestamps::fromDatabase((string) $row['revoked_at']),
        );
    }
}
