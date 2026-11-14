<?php

declare(strict_types=1);

namespace App\Events\Domain;

/**
 * The processing-fee estimate a public buyer pays on top of the ticket price (D4, D6). All arithmetic is integer: the rate is
 * grossed up so that, after the provider takes its percentage (plus its own VAT on that fee), the church keeps the ticket price.
 */
final readonly class TicketFeeSettings
{
    private const int MILLION = 1_000_000;

    public function __construct(
        public int $feeRatePpm,
        public int $feeFixedMinor,
        public int $providerVatPpm,
        public int $holdMinutes,
        public int $version,
    ) {
        if ($feeRatePpm < 0 || $feeFixedMinor < 0 || $feeFixedMinor > 100_000 || $providerVatPpm < 0 || $providerVatPpm > 500_000 || $holdMinutes < 5 || $holdMinutes > 60 || $version < 0) {
            throw EventException::invalid('Check the processing-fee rate, fixed fee, provider VAT and hold minutes.');
        }
        if ($this->effectiveRatePpm() >= self::MILLION) {
            throw EventException::invalid('The processing-fee rate must be below 100 %.');
        }
    }

    /** The provider's rate including VAT on its fee, rounded up: 29 500 ppm with 15 % VAT is 33 925 ppm. */
    public function effectiveRatePpm(): int
    {
        return intdiv($this->feeRatePpm * (self::MILLION + $this->providerVatPpm) + self::MILLION - 1, self::MILLION);
    }

    /** What the buyer pays for a ticket subtotal. A free order (subtotal zero) carries no fee. */
    public function total(int $subtotalMinor): int
    {
        if ($subtotalMinor <= 0) {
            return 0;
        }
        $denominator = self::MILLION - $this->effectiveRatePpm();

        return intdiv(($subtotalMinor + $this->feeFixedMinor) * self::MILLION + $denominator - 1, $denominator);
    }

    public function fee(int $subtotalMinor): int
    {
        return $this->total($subtotalMinor) - max(0, $subtotalMinor);
    }

    public function change(int $feeRatePpm, int $feeFixedMinor, int $providerVatPpm, int $holdMinutes): self
    {
        return new self($feeRatePpm, $feeFixedMinor, $providerVatPpm, $holdMinutes, $this->version + 1);
    }
}
