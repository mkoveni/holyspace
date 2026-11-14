<?php

declare(strict_types=1);

namespace App\Tests\Events\Domain;

use App\Events\Domain\EventException;
use App\Events\Domain\TicketFeeSettings;
use PHPUnit\Framework\TestCase;

final class TicketFeeSettingsTest extends TestCase
{
    private function yocoCore(): TicketFeeSettings
    {
        return new TicketFeeSettings(29500, 0, 150000, 15, 0);
    }

    public function testTheDocumentedWorkedExampleIsReproducedExactly(): void
    {
        $fees = $this->yocoCore();
        self::assertSame(33925, $fees->effectiveRatePpm());
        self::assertSame(20703, $fees->total(20000));
        self::assertSame(703, $fees->fee(20000));
    }

    public function testTheChurchKeepsAtLeastTheTicketPriceAfterTheProviderTakesItsShare(): void
    {
        $fees = $this->yocoCore();
        foreach ([500, 501, 999, 1234, 5000, 19999, 123456, 99999999] as $subtotal) {
            $total = $fees->total($subtotal);
            $providerTakes = (int) ceil($total * $fees->effectiveRatePpm() / 1_000_000);
            self::assertGreaterThanOrEqual($subtotal, $total - $providerTakes, "subtotal $subtotal");
            self::assertSame($total - $subtotal, $fees->fee($subtotal));
        }
    }

    public function testAFreeOrderCarriesNoFeeEvenWithAFixedFee(): void
    {
        $fees = new TicketFeeSettings(29500, 100, 150000, 15, 0);
        self::assertSame(0, $fees->total(0));
        self::assertSame(0, $fees->fee(0));
        self::assertGreaterThan(1000, $fees->total(1000), 'a fixed fee is grossed up too');
    }

    public function testAZeroRateAddsOnlyTheFixedFee(): void
    {
        self::assertSame(1100, (new TicketFeeSettings(0, 100, 0, 15, 0))->total(1000));
    }

    public function testNonsenseSettingsAreRefused(): void
    {
        foreach ([[1_000_000, 0, 0, 15], [29500, -1, 0, 15], [29500, 0, 0, 4], [29500, 0, 0, 61], [29500, 0, 600000, 15]] as [$rate, $fixed, $vat, $hold]) {
            try {
                new TicketFeeSettings($rate, $fixed, $vat, $hold, 0);
                self::fail("accepted $rate/$fixed/$vat/$hold");
            } catch (EventException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
