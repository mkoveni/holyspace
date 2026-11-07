<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\Events\Application\Command\CreateEventVenue\CreateEventVenueCommand;
use App\Events\Application\Command\CreateEventVenue\CreateEventVenueHandler;
use App\Events\Application\Command\UpdateEventVenue\UpdateEventVenueCommand;
use App\Events\Application\Command\UpdateEventVenue\UpdateEventVenueHandler;
use App\Events\Domain\EventException;
use App\IdentityAccess\Domain\IdentityException;
use App\Tests\People\Application\SequentialIds;
use App\Tests\Support\AllowedPermissions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** Handler logic in isolation: no database, no transaction, no identity context. */
final class VenueHandlersTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';

    private InMemoryVenueRepository $venues;
    private RecordingJournal $journal;

    protected function setUp(): void
    {
        $this->venues = new InMemoryVenueRepository();
        $this->journal = new RecordingJournal();
    }

    private function create(bool $manage = true): string
    {
        return (new CreateEventVenueHandler(
            new AllowedPermissions($manage ? ['events.manage'] : ['events.read']),
            $this->venues,
            $this->journal,
            new MockClock('2026-06-01 10:00:00 UTC'),
            new SequentialIds(),
        ))->handle(new CreateEventVenueCommand(self::ACTOR, 'Main hall', '1 Church St', true));
    }

    private function update(int $version, bool $manage = true, string $name = 'Annex'): void
    {
        $id = array_key_first($this->venues->venues) ?? '00000000-0000-4000-8000-0000000000ff';
        (new UpdateEventVenueHandler(
            new AllowedPermissions($manage ? ['events.manage'] : ['events.read']),
            $this->venues,
            $this->journal,
            new MockClock('2026-06-01 10:00:00 UTC'),
        ))->handle(new UpdateEventVenueCommand(self::ACTOR, $id, $version, $name, '', true));
    }

    public function testCreatingStoresTheVenueAndAuditsIt(): void
    {
        $id = $this->create();

        self::assertSame('Main hall', $this->venues->venues[$id]->name);
        self::assertSame([self::ACTOR.":venue.created:$id"], $this->journal->entries);
    }

    public function testCreatingChecksPermissionFirst(): void
    {
        try {
            $this->create(manage: false);
            self::fail('Expected a permission failure.');
        } catch (IdentityException) {
            self::assertSame([], $this->venues->venues);
            self::assertSame([], $this->journal->entries);
        }
    }

    public function testUpdatingSavesTheNextVersionAndAudits(): void
    {
        $id = $this->create();
        $this->update(0);

        self::assertSame(1, $this->venues->venues[$id]->version);
        self::assertSame(self::ACTOR.":venue.updated:$id", $this->journal->entries[1]);
    }

    public function testUpdatingRejectsAStaleVersionWithoutAuditing(): void
    {
        $this->create();
        try {
            $this->update(4);
            self::fail('Expected a stale version failure.');
        } catch (EventException) {
            self::assertCount(1, $this->journal->entries);
        }
    }

    public function testUpdatingChecksPermissionBeforeLookingUpTheVenue(): void
    {
        $this->expectException(IdentityException::class);

        $this->update(0, manage: false);
    }

    public function testUpdatingAnUnknownVenueIsNotFound(): void
    {
        $this->expectException(EventException::class);
        $this->expectExceptionMessage('Venue was not found.');

        $this->update(0);
    }
}
