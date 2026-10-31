<?php

declare(strict_types=1);

namespace App\Giving\Domain\ValueObject;

use InvalidArgumentException;
use Symfony\Component\Intl\Currencies;

final readonly class Money
{
    public function __construct(public int $amountMinor, public string $currency)
    {
        if ($amountMinor < 1 || $amountMinor > 1000000000000) {
            throw new InvalidArgumentException('Amount must be a positive integer in minor units, at most 1,000,000,000,000.');
        }
        if (!preg_match('/^[A-Z]{3}$/', $currency) || !Currencies::exists($currency)) {
            throw new InvalidArgumentException('Currency must be a three-letter uppercase code.');
        }
    }
}
