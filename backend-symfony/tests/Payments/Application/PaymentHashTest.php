<?php

declare(strict_types=1);

namespace App\Tests\Payments\Application;

use App\Payments\Application\PaymentHash;
use App\Shared\Domain\CalendarDate;
use PHPUnit\Framework\TestCase;

/** Java-captured vectors (docs/porting/vectors/PaymentVectors.java): request, notification and review hashes Java stored must replay here. */
final class PaymentHashTest extends TestCase
{
    private const string ACTOR = '11111111-1111-4111-8111-111111111111';
    private const string DONOR = '22222222-2222-4222-8222-222222222222';
    private const string FUND = '33333333-3333-4333-8333-333333333333';
    private const string ORDER = '44444444-4444-4444-8444-444444444444';
    private const string CHARGE = '55555555-5555-4555-8555-555555555555';
    private const string TOKEN = '12345678-1234-1234-1234-123456789012';

    public function testMatchesJavaPaymentHash(): void
    {
        self::assertSame('88a1d0f623f7248ae44d17dc2fea6cf66267152746838a429df310218717eadf', PaymentHash::of('checkout', self::ACTOR, self::DONOR, self::FUND, 10000, 'payfast', 'live', false));
        self::assertSame('6e0c31703dfc80d195666f49d6c370d7188d0c9a02e51ad8ed8f4ad19b27a008', PaymentHash::of('donor-checkout', self::ACTOR, self::DONOR, self::FUND, 25050, 'yoco', 'test', true));
        self::assertSame('0ca71189ab4eeed2194aedf3bb0f336c8bb16ce6313846fb8be678d76acf6bfe', PaymentHash::of(self::TOKEN));
        self::assertSame('35145664cd302def95eb86c83a673104f533cece01ea1f67b083376d1a86203d', PaymentHash::of('payfast', 'live', 'PAID', '12345', null, self::ORDER, 10000, 'ZAR', PaymentHash::of(self::TOKEN), CalendarDate::of(2026, 10, 28)));
        self::assertSame('61682c987dd0d9dfcc2a33bde4b436dc5a0cbe8a5907f86b9ec77d60a622fadb', PaymentHash::of('settlement', self::ACTOR, self::ORDER, self::CHARGE, 0, 'BANK-001', 300, 9700, 'Matched bank and provider statement'));
        self::assertSame('6536762c266511b4732243abee6a075bfa7dc105345d40656d6a52fe0b17546e', PaymentHash::of(self::ACTOR, 'Grüße 漢字 😀', '', null));
        self::assertSame('c80a5bd35c4ff7846fa310b8cac3bd2839e43aeb4c26148b6c9858a803bdd4cd', PaymentHash::of('10000100', 'merchant-key', 'passphrase', '', ''));
        self::assertSame('50d6fbb58eaed31c9d9c37e2cef3d0bcac3cc7f6b3ef95b6cd3c809d4cca696f', PaymentHash::of(self::ACTOR, 'test', 'REMOVE', 'sub_old'));
        self::assertSame('f3e0466234e9ed671be25b024d1cacc62b12610a9acbb22e2f63ea6ee626b219', PaymentHash::of(self::ACTOR, 'live', 'REGISTER', null));
    }
}
