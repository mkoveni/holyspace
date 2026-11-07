<?php

declare(strict_types=1);

namespace App\Giving\Domain;

/**
 * A positive amount in integer minor units (cents) of a supported two-decimal currency.
 * Amounts are never floats; PHP's 64-bit int holds the 10^12 ceiling with room to sum report lines.
 */
final readonly class Money
{
    public const array CURRENCIES = ['ZAR', 'USD', 'EUR', 'GBP'];
    public const int MAX_MINOR = 1_000_000_000_000;

    private function __construct(public int $amountMinor, public string $currency)
    {
    }

    public static function of(int $amountMinor, string $currency): self
    {
        if ($amountMinor < 1 || $amountMinor > self::MAX_MINOR) {
            throw GivingException::invalid('Enter a positive amount of at most 10,000,000,000.00.');
        }
        self::currency($currency);

        return new self($amountMinor, $currency);
    }

    /** Rejects anything but ZAR, USD, EUR or GBP; used where only the currency is validated. */
    public static function currency(?string $currency): string
    {
        if (!\in_array($currency, self::CURRENCIES, true)) {
            throw GivingException::invalid('Choose ZAR, USD, EUR or GBP. All supported currencies use two decimal places.');
        }

        return $currency;
    }

    /** The Java record's toString, which feeds request hashes: Money[amountMinor=1500, currency=ZAR]. */
    public function javaString(): string
    {
        return sprintf('Money[amountMinor=%d, currency=%s]', $this->amountMinor, $this->currency);
    }
}
