<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use DateInterval;
use DateTimeImmutable;

/** The default and the limit of a session search: from a day ago for a week, never more than 31 days. */
final class SessionWindow
{
    private function __construct()
    {
    }

    /** @return array{DateTimeImmutable, DateTimeImmutable} */
    public static function resolve(?DateTimeImmutable $from, ?DateTimeImmutable $until, DateTimeImmutable $now): array
    {
        $from ??= $now->sub(new DateInterval('P1D'));
        $until ??= $from->add(new DateInterval('P7D'));
        ChildrenRules::window($from, $until);
        if ($until > $from->add(new DateInterval('P31D'))) {
            throw ChildrenException::invalid('Search at most 31 days at a time.');
        }

        return [$from, $until];
    }
}
