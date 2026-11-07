<?php

declare(strict_types=1);

namespace App\Tests\Giving\Domain;

use App\Giving\Domain\GivingException;
use App\Giving\Domain\Money;
use App\Shared\Domain\ErrorKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testAcceptsTheEdgesOfTheRange(): void
    {
        self::assertSame(1, Money::of(1, 'ZAR')->amountMinor);
        self::assertSame(1_000_000_000_000, Money::of(1_000_000_000_000, 'GBP')->amountMinor);
    }

    /** @return iterable<string, array{int}> */
    public static function outOfRange(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'above ten billion' => [1_000_000_000_001];
    }

    #[DataProvider('outOfRange')]
    public function testRejectsAmountsOutsideTheRange(int $amount): void
    {
        $this->expectExceptionObject(GivingException::invalid('Enter a positive amount of at most 10,000,000,000.00.'));

        Money::of($amount, 'ZAR');
    }

    public function testAcceptsOnlyTheFourTwoDecimalCurrencies(): void
    {
        foreach (['ZAR', 'USD', 'EUR', 'GBP'] as $currency) {
            self::assertSame($currency, Money::of(100, $currency)->currency);
        }

        foreach (['zar', 'JPY', '', 'ZARX'] as $currency) {
            try {
                Money::of(100, $currency);
                self::fail("$currency was accepted");
            } catch (GivingException $e) {
                self::assertSame(ErrorKind::Invalid, $e->kind());
                self::assertSame('Choose ZAR, USD, EUR or GBP. All supported currencies use two decimal places.', $e->getMessage());
            }
        }
    }

    public function testCurrencyAloneIsValidatedWithTheSameMessage(): void
    {
        self::assertSame('EUR', Money::currency('EUR'));

        $this->expectExceptionObject(GivingException::invalid('Choose ZAR, USD, EUR or GBP. All supported currencies use two decimal places.'));
        Money::currency(null);
    }

    public function testRendersLikeTheJavaRecord(): void
    {
        self::assertSame('Money[amountMinor=1500, currency=ZAR]', Money::of(1500, 'ZAR')->javaString());
    }
}
