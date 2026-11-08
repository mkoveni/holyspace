<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure\Persistence;

use App\Volunteering\Domain\VolunteerException;
use Doctrine\DBAL\Exception\DriverException;

/** Java maps every `DuplicateKeyException` in Volunteering to one 409. */
final class ServingWrites
{
    private function __construct()
    {
    }

    /** @param callable(): (int|string) $write */
    public static function guard(callable $write): int
    {
        try {
            return (int) $write();
        } catch (DriverException $error) {
            if ('23505' === $error->getSQLState()) {
                throw VolunteerException::clash();
            }
            throw $error;
        }
    }

    /** A versioned save that changed nothing lost a race. */
    public static function saved(int $rows): void
    {
        if (1 !== $rows) {
            throw VolunteerException::conflict('This record changed. Refresh before saving.');
        }
    }
}
