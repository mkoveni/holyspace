<?php

declare(strict_types=1);

namespace App\Reporting\Domain\Model;

use App\Shared\Domain\ValueObject\Input;
use InvalidArgumentException;

final class SegmentCriteria
{
    public static function validate(array $filters): void
    {
        if (array_diff(array_keys($filters), ['status', 'ageGroup', 'ministryId', 'attendanceStatus', 'lookbackDays', 'from', 'to'])) {
            throw new InvalidArgumentException('Unsupported segment filter.');
        }
        if (isset($filters['status']) && !in_array($filters['status'], ['prospect', 'active', 'inactive', 'transferred', 'deceased'], true)) {
            throw new InvalidArgumentException('Invalid membership status.');
        }
        if (isset($filters['ageGroup']) && !in_array($filters['ageGroup'], ['adult', 'child'], true)) {
            throw new InvalidArgumentException('Invalid age group.');
        }
        if (isset($filters['ministryId'])) {
            if (!is_string($filters['ministryId'])) {
                throw new InvalidArgumentException('Invalid ministry.');
            } Input::id($filters['ministryId']);
        }
        if (isset($filters['attendanceStatus']) && !in_array($filters['attendanceStatus'], ['present', 'absent', 'no_recorded_presence'], true)) {
            throw new InvalidArgumentException('Invalid attendance filter.');
        }
        if (isset($filters['lookbackDays']) && (!is_int($filters['lookbackDays']) || $filters['lookbackDays'] < 1 || $filters['lookbackDays'] > 366)) {
            throw new InvalidArgumentException('Lookback must be 1 to 366 days.');
        }
        if (isset($filters['attendanceStatus'])) {
            if (isset($filters['lookbackDays'])) {
                if (isset($filters['from']) || isset($filters['to'])) {
                    throw new InvalidArgumentException('Choose a rolling period or explicit dates.');
                }
            } else {
                if (!isset($filters['from'], $filters['to']) || !is_string($filters['from']) || !is_string($filters['to'])) {
                    throw new InvalidArgumentException('Attendance filters require a period.');
                }
                self::period($filters['from'], $filters['to']);
            }
        } elseif (isset($filters['lookbackDays']) || isset($filters['from']) || isset($filters['to'])) {
            throw new InvalidArgumentException('Dates require an attendance filter.');
        }
    }

    public static function period(string $from, string $to): void
    {
        $start = Input::date($from);
        $end = Input::date($to);
        if ($end < $start || $start->diff($end)->days > 366) {
            throw new InvalidArgumentException('Choose an ordered period of at most 367 days.');
        }
    }

    public static function permissions(array $filters): array
    {
        self::validate($filters);

        return ['people.read', ...(isset($filters['ministryId']) ? ['ministries.manage'] : []), ...(isset($filters['attendanceStatus']) ? ['attendance.manage'] : [])];
    }
}
