<?php

declare(strict_types=1);

namespace App\Tests\Giving\Domain;

use App\Giving\Domain\Acknowledgement;
use App\Giving\Domain\AcknowledgementLine;
use App\Giving\Domain\Allocation;
use App\Giving\Domain\Gift;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Money;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AcknowledgementTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';
    private const string FUND = '00000000-0000-4000-8000-000000000001';
    private const string OTHER = '00000000-0000-4000-8000-000000000002';

    private static function gift(): Gift
    {
        return Gift::reconstitute('00000000-0000-4000-8000-0000000000aa', null, Money::of(10000, 'ZAR'), CalendarDate::of(2026, 1, 5), 'BANK', null, [new Allocation(self::OTHER, 3000), new Allocation(self::FUND, 7000)], self::ACTOR, new DateTimeImmutable('2026-01-05T08:00:00Z'));
    }

    /** @param array<string, string> $names */
    private static function issue(?string $donorName = 'Thandi Mabasa', array $names = [self::FUND => 'General', self::OTHER => 'Missions']): Acknowledgement
    {
        return Acknowledgement::issue('00000000-0000-4000-8000-0000000000c1', self::gift(), $donorName, $names, CalendarDate::of(2026, 1, 6), self::ACTOR, new DateTimeImmutable('2026-01-06T08:00:00Z'));
    }

    public function testFreezesOneNamedLinePerAllocationInTheGiftsOrder(): void
    {
        $acknowledgement = self::issue(' Thandi Mabasa ');

        self::assertSame('00000000-0000-4000-8000-0000000000aa', $acknowledgement->giftId);
        self::assertSame('Thandi Mabasa', $acknowledgement->donorName, 'trimmed as Java GivingRules.text');
        self::assertEquals([new AcknowledgementLine(self::OTHER, 'Missions', 3000), new AcknowledgementLine(self::FUND, 'General', 7000)], $acknowledgement->lines);
    }

    public function testTheDonorNameFollowsJavasTextRule(): void
    {
        self::assertSame(str_repeat('n', 300), self::issue(str_repeat('n', 300))->donorName);
        foreach ([null, ' ', str_repeat('n', 301)] as $name) {
            try {
                self::issue($name);
                self::fail('accepted donor name '.var_export($name, true));
            } catch (GivingException $error) {
                self::assertStringStartsWith('Donor name', $error->getMessage());
            }
        }
    }

    public function testAnAllocationWithoutAKnownFundNameIsMissing(): void
    {
        $this->expectException(GivingException::class);
        $this->expectExceptionMessage('The Giving record was not found.');

        self::issue(names: [self::FUND => 'General']);
    }
}
