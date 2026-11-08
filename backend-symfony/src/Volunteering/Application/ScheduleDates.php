<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

use App\Volunteering\Application\Contract\ShiftDraft;
use App\Volunteering\Domain\ServingRules;
use App\Volunteering\Domain\VolunteerException;
use App\Volunteering\Domain\ZonedTime;
use DateTimeImmutable;

/**
 * Java `ScheduleDates`: standalone shift drafts for 1-25 dates at one local time in an explicit zone. An end time at or
 * before the start ends on the next day. Each draft's request id is derived from the preview request and the date
 * (Java `UUID.nameUUIDFromBytes`), so applying the same preview twice creates nothing new.
 */
final class ScheduleDates
{
    private function __construct()
    {
    }

    /**
     * @param list<string|null>|null $dates ISO dates (Y-m-d)
     *
     * @return list<ShiftDraft> by date
     */
    public static function preview(?string $request, ?string $team, ?string $role, ?array $dates, ?string $start, ?string $end, ?string $timeZone, int $positions): array
    {
        $request = ServingRules::id($request);
        ServingRules::id($team);
        ServingRules::id($role);
        if (null === $dates || [] === $dates || \count($dates) > 25 || \in_array(null, $dates, true)
            || \count(array_unique($dates)) !== \count($dates) || null === $start || null === $end || $start === $end
            || $positions < 1 || $positions > 100) {
            throw VolunteerException::invalid('Choose 1–25 unique dates, times and 1–100 positions.');
        }
        $zone = ServingRules::zone((string) $timeZone);
        /* @var list<string> $dates */
        sort($dates);
        $drafts = [];
        foreach ($dates as $day) {
            $endDay = $end > $start ? $day : new DateTimeImmutable($day.'T00:00:00Z')->modify('+1 day')->format('Y-m-d');
            $from = ZonedTime::resolve($day.' '.$start, $zone);
            $until = ZonedTime::resolve($endDay.' '.$end, $zone);
            if (null === $from || null === $until) {
                throw VolunteerException::invalid('A selected local time is ambiguous or does not exist because of daylight saving. Choose a different time.');
            }
            $drafts[] = new ShiftDraft(self::nameUuid($request.':'.$day), $team, $role, 'STANDALONE', null, $from, $until, $positions);
        }

        return $drafts;
    }

    /** Java `UUID.nameUUIDFromBytes`: an MD5 (version 3) UUID of the UTF-8 bytes, without a namespace. */
    public static function nameUuid(string $name): string
    {
        $hash = md5($name, true);
        $hash[6] = \chr((\ord($hash[6]) & 0x0F) | 0x30);
        $hash[8] = \chr((\ord($hash[8]) & 0x3F) | 0x80);
        $hex = bin2hex($hash);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }
}
