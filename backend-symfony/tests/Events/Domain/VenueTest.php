<?php

declare(strict_types=1);

namespace App\Tests\Events\Domain;

use App\Events\Domain\EventException;
use App\Events\Domain\Venue;
use PHPUnit\Framework\TestCase;

final class VenueTest extends TestCase
{
    private const string ID = '00000000-0000-4000-8000-000000000001';

    public function testANewVenueIsActiveAtVersionZeroWithTrimmedText(): void
    {
        $venue = Venue::create(self::ID, '  Main hall ', ' 1 Church St ', true);

        self::assertSame('Main hall', $venue->name);
        self::assertSame('1 Church St', $venue->address);
        self::assertTrue($venue->active);
        self::assertSame(0, $venue->version);
    }

    public function testAddressIsOptionalButNameIsRequiredAndBounded(): void
    {
        self::assertSame('', Venue::create(self::ID, 'Hall', '  ', false)->address);

        foreach ([' ', str_repeat('a', 151)] as $name) {
            try {
                Venue::create(self::ID, $name, '', false);
                self::fail('Expected the name to be rejected.');
            } catch (EventException $e) {
                self::assertSame('Enter text of 1–150 characters.', $e->getMessage());
            }
        }
        $this->expectException(EventException::class);
        Venue::create(self::ID, 'Hall', str_repeat('a', 1001), false);
    }

    public function testEditAdvancesTheVersionAndKeepsExclusivity(): void
    {
        $edited = Venue::create(self::ID, 'Hall', '', true)->edit('Annex', 'Rear', false, 0);

        self::assertSame(1, $edited->version);
        self::assertTrue($edited->exclusive);
        self::assertFalse($edited->active);
        self::assertSame('Annex', $edited->name);
    }

    public function testEditRejectsAStaleVersion(): void
    {
        $this->expectException(EventException::class);
        $this->expectExceptionMessage('These details changed. Refresh and review before saving.');

        Venue::create(self::ID, 'Hall', '', true)->edit('Annex', '', true, 3);
    }
}
