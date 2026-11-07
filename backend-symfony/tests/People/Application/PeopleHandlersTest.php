<?php

declare(strict_types=1);

namespace App\Tests\People\Application;

use App\IdentityAccess\Domain\IdentityException;
use App\People\Application\Command\CreateRelationship\CreateRelationshipCommand;
use App\People\Application\Command\CreateRelationship\CreateRelationshipHandler;
use App\People\Application\Command\RegisterPerson\RegisterPersonCommand;
use App\People\Application\Command\RegisterPerson\RegisterPersonHandler;
use App\People\Application\Command\SetPersonArchived\SetPersonArchivedCommand;
use App\People\Application\Command\SetPersonArchived\SetPersonArchivedHandler;
use App\People\Domain\PeopleException;
use App\People\Domain\ValueObject\MembershipType;
use App\People\Domain\ValueObject\PersonDetails;
use App\People\Domain\ValueObject\RelationshipType;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Domain\CalendarDate;
use App\Shared\Domain\StaleRevisionException;
use App\Tests\Support\AllowedPermissions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** Handler logic in isolation: no database, no transaction (the command bus owns that), no identity context. */
final class PeopleHandlersTest extends TestCase
{
    private const string ACTOR = '99999999-9999-4999-8999-999999999999';

    private RecordingAudit $audit;
    private InMemoryPersonRepository $people;
    private InMemoryRelationshipRepository $relationships;
    private SequentialIds $ids;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->audit = new RecordingAudit();
        $this->people = new InMemoryPersonRepository();
        $this->relationships = new InMemoryRelationshipRepository();
        $this->ids = new SequentialIds();
        $this->clock = new MockClock('2026-06-01 22:30:00 UTC');
    }

    public function testRegisteringStoresThePersonAndAuditsIt(): void
    {
        $id = $this->register(new PersonDetails('dr', 'Ada', 'Lovelace', null, null, null, null), manage: true);

        self::assertSame('DR', $this->people->people[$id]->details->titleCode);
        self::assertSame(MembershipType::Visitor, $this->people->people[$id]->membershipType);
        self::assertSame([self::ACTOR.":person.registered:$id"], $this->audit->entries);
    }

    public function testRegisteringChecksThePermissionBeforeDoingAnything(): void
    {
        try {
            $this->register(new PersonDetails(null, 'Ada', 'Lovelace', null, null, null, null), manage: false);
            self::fail('Expected a permission failure.');
        } catch (IdentityException) {
            self::assertSame([], $this->people->people);
            self::assertSame([], $this->audit->entries);
        }
    }

    public function testUnknownTitlesAreRejected(): void
    {
        $this->expectException(PeopleException::class);
        $this->expectExceptionMessage('Select an existing title.');

        $this->register(new PersonDetails('PROF', 'Ada', 'Lovelace', null, null, null, null), manage: true);
    }

    public function testBirthDatesAreCheckedAgainstTheChurchsToday(): void
    {
        // 22:30 UTC on 1 June is already 2 June in Johannesburg, so a 2 June birth date is today, not the future.
        $id = $this->register(new PersonDetails(null, 'Ada', 'Lovelace', CalendarDate::parse('2026-06-02'), null, null, null), manage: true);

        self::assertArrayHasKey($id, $this->people->people);
    }

    public function testArchivingChecksTheExpectedVersionAndAuditsOnlyOnSuccess(): void
    {
        $id = $this->register(new PersonDetails(null, 'Ada', 'Lovelace', null, null, null, null), manage: true);
        $handler = new SetPersonArchivedHandler(new AllowedPermissions(['people.manage']), $this->audit, $this->clock, $this->people);

        try {
            $handler->handle(new SetPersonArchivedCommand(self::ACTOR, $id, 7, true));
            self::fail('Expected a stale version.');
        } catch (StaleRevisionException) {
            self::assertNull($this->people->people[$id]->archivedAt);
        }

        $handler->handle(new SetPersonArchivedCommand(self::ACTOR, $id, 0, true));

        self::assertNotNull($this->people->people[$id]->archivedAt);
        self::assertSame([self::ACTOR.":person.registered:$id", self::ACTOR.":person.archived:$id"], $this->audit->entries);
    }

    public function testRelationshipsLockTheGraphAndRefuseParentageCycles(): void
    {
        $a = $this->register(new PersonDetails(null, 'Ada', 'A', null, null, null, null), manage: true);
        $b = $this->register(new PersonDetails(null, 'Bob', 'B', null, null, null, null), manage: true);
        $handler = new CreateRelationshipHandler(new AllowedPermissions(['people.manage']), $this->audit, $this->clock, $this->relationships, $this->people, $this->ids);

        $handler->handle(new CreateRelationshipCommand(self::ACTOR, $a, $b, RelationshipType::ParentOf));

        self::assertCount(1, $this->relationships->relationships);
        self::assertSame(1, $this->relationships->locks);
        $this->expectExceptionMessage('Parentage cannot contain a cycle.');
        $handler->handle(new CreateRelationshipCommand(self::ACTOR, $b, $a, RelationshipType::ParentOf));
    }

    public function testRelatingAnUnknownPersonIsNotFound(): void
    {
        $a = $this->register(new PersonDetails(null, 'Ada', 'A', null, null, null, null), manage: true);
        $handler = new CreateRelationshipHandler(new AllowedPermissions(['people.manage']), $this->audit, $this->clock, $this->relationships, $this->people, $this->ids);

        $this->expectExceptionMessage('Person was not found.');
        $handler->handle(new CreateRelationshipCommand(self::ACTOR, $a, '00000000-0000-4000-8000-0000000000aa', RelationshipType::Other));
    }

    private function register(PersonDetails $details, bool $manage): string
    {
        $handler = new RegisterPersonHandler(
            new AllowedPermissions($manage ? ['people.manage'] : ['people.read']),
            $this->audit,
            $this->clock,
            $this->people,
            new FixedTitles(['DR']),
            new ChurchCalendar($this->clock, 'Africa/Johannesburg'),
            $this->ids,
        );

        return $handler->handle(new RegisterPersonCommand(self::ACTOR, $details, null));
    }
}
