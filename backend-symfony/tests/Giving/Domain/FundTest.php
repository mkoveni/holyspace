<?php

declare(strict_types=1);

namespace App\Tests\Giving\Domain;

use App\Giving\Domain\Fund;
use App\Giving\Domain\GivingException;
use PHPUnit\Framework\TestCase;

final class FundTest extends TestCase
{
    private const string ID = '00000000-0000-4000-8000-000000000001';

    public function testCreatingTrimsTextAndStartsActiveAtVersionZero(): void
    {
        $fund = Fund::create(self::ID, '  General ', "  Shared needs\n");

        self::assertSame('General', $fund->name);
        self::assertSame('Shared needs', $fund->description);
        self::assertTrue($fund->active);
        self::assertSame(0, $fund->version);
    }

    public function testAMissingDescriptionIsEmpty(): void
    {
        self::assertSame('', Fund::create(self::ID, 'General', null)->description);
    }

    public function testTheNameIsRequiredAndAtMost100Characters(): void
    {
        self::assertSame(str_repeat('a', 100), Fund::create(self::ID, str_repeat('a', 100), '')->name);
        foreach ([null, '', '   ', str_repeat('a', 101), str_repeat('😀', 51)] as $name) {
            try {
                Fund::create(self::ID, $name, '');
                self::fail('Expected an invalid name: '.var_export($name, true));
            } catch (GivingException $e) {
                self::assertSame('Fund name is required and must be at most 100 characters.', $e->getMessage());
            }
        }
    }

    public function testTheDescriptionIsAtMost1000Characters(): void
    {
        self::assertSame(1000, \strlen(Fund::create(self::ID, 'General', str_repeat('a', 1000))->description));
        $this->expectException(GivingException::class);
        $this->expectExceptionMessage('Invalid fund details.');

        Fund::create(self::ID, 'General', str_repeat('a', 1001));
    }

    public function testRevisingAdvancesTheVersionAndCanArchive(): void
    {
        $revised = Fund::create(self::ID, 'General', '')->revise(0, ' Missions ', 'Abroad', false);

        self::assertSame(self::ID, $revised->id);
        self::assertSame('Missions', $revised->name);
        self::assertSame('Abroad', $revised->description);
        self::assertFalse($revised->active);
        self::assertSame(1, $revised->version);
    }

    public function testRevisingAStaleVersionIsAConflict(): void
    {
        $this->expectException(GivingException::class);
        $this->expectExceptionMessage('This record changed. Refresh before trying again.');

        Fund::reconstitute(self::ID, 'General', '', true, 2)->revise(1, 'General', '', true);
    }

    public function testRevisingStillValidatesTheName(): void
    {
        $this->expectException(GivingException::class);

        Fund::create(self::ID, 'General', '')->revise(0, ' ', '', true);
    }
}
