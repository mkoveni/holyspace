<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

use App\Volunteering\Domain\ServingRules;
use App\Volunteering\Domain\VolunteerException;
use DateTimeImmutable;

/** Java `ServingViews` bounds shared by the Volunteering lists. */
final class ServingViews
{
    private function __construct()
    {
    }

    public static function page(int $limit, int $offset): void
    {
        if ($limit < 1 || $limit > 100 || $offset < 0 || $offset > 100000) {
            throw VolunteerException::invalid('Choose a bounded page of 1–100 records.');
        }
    }

    /**
     * An interval of at most 90 days.
     *
     * @return array{DateTimeImmutable, DateTimeImmutable}
     */
    public static function range(?DateTimeImmutable $from, ?DateTimeImmutable $until): array
    {
        $from = ServingRules::instant($from);
        $until = ServingRules::instant($until);
        ServingRules::period($from, $until);
        if ($until > $from->modify('+'.(90 * 86400).' seconds')) {
            throw VolunteerException::invalid('Choose at most 90 days.');
        }

        return [$from, $until];
    }
}
