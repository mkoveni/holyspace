<?php

declare(strict_types=1);

namespace App\Tests\People\Domain;

use App\People\Domain\AgePolicy;
use App\People\Domain\ValueObject\AgeCategory;
use App\Shared\Domain\CalendarDate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AgePolicyTest extends TestCase
{
    /** @return iterable<string, array{?string, string, AgeCategory}> */
    public static function categories(): iterable
    {
        yield 'turns 18 today' => ['2008-10-26', '2026-10-26', AgeCategory::Adult];
        yield 'turns 18 tomorrow' => ['2008-10-26', '2026-10-25', AgeCategory::Child];
        yield 'born today' => ['2026-10-26', '2026-10-26', AgeCategory::Child];
        yield 'born in the future' => ['2026-10-27', '2026-10-26', AgeCategory::Unknown];
        yield 'no birth date' => [null, '2026-10-26', AgeCategory::Unknown];
        yield 'leap day baby is an adult on Feb 28 of a non-leap year' => ['2008-02-29', '2026-02-28', AgeCategory::Adult];
        yield 'leap day baby is a child the day before' => ['2008-02-29', '2026-02-27', AgeCategory::Child];
        yield 'leap day baby in a leap year' => ['2008-02-29', '2026-03-01', AgeCategory::Adult];
        yield 'born Feb 28 same year' => ['2008-02-28', '2026-02-28', AgeCategory::Adult];
        yield 'born Mar 1 of a leap year' => ['2008-03-01', '2026-02-28', AgeCategory::Child];
    }

    #[DataProvider('categories')]
    public function testCategory(?string $birth, string $asOf, AgeCategory $expected): void
    {
        self::assertSame($expected, AgePolicy::category(null === $birth ? null : CalendarDate::parse($birth), CalendarDate::parse($asOf)));
    }

    public function testCutoffIsEighteenYearsBack(): void
    {
        self::assertSame('2008-10-26', AgePolicy::adultBirthDateCutoff(CalendarDate::parse('2026-10-26'))->iso());
        self::assertSame('2008-02-29', AgePolicy::adultBirthDateCutoff(CalendarDate::parse('2026-02-28'))->iso());
        self::assertSame('2012-02-29', AgePolicy::adultBirthDateCutoff(CalendarDate::parse('2030-02-28'))->iso(), '2012 is a leap year');
    }
}
