<?php

declare(strict_types=1);

namespace App\Giving\Application;

use App\Giving\Application\Contract\GiftFilter;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\GivingRules;
use App\Giving\Domain\Money;

/** Bounds every ledger read: a page, a period of at most ten years and a supported currency. */
final class GivingFilters
{
    public const int MAX_PERIOD_DAYS = 3660;

    private function __construct()
    {
    }

    public static function validate(GiftFilter $filter): void
    {
        GivingRules::page($filter->limit, $filter->offset);
        $from = $filter->from;
        $to = $filter->to;
        if (null === $from || null === $to || $to->isBefore($from) || $from->year() < 1900 || $to->year() > 9999
            || $from->daysUntil($to) > self::MAX_PERIOD_DAYS) {
            throw GivingException::invalid('Choose a period of at most ten years, with the end on or after the start.');
        }
        if (null !== $filter->currency && !\in_array($filter->currency, Money::CURRENCIES, true)) {
            throw GivingException::invalid('Choose a supported currency.');
        }
    }
}
