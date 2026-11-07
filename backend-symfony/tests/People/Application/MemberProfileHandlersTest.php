<?php

declare(strict_types=1);

namespace App\Tests\People\Application;

use App\IdentityAccess\Domain\IdentityException;
use App\People\Application\Command\RemoveMyContact\RemoveMyContactCommand;
use App\People\Application\Command\RemoveMyContact\RemoveMyContactHandler;
use App\People\Application\Command\SaveMyContact\SaveMyContactCommand;
use App\People\Application\Command\SaveMyContact\SaveMyContactHandler;
use App\People\Application\Command\UpdateMyProfile\UpdateMyProfileCommand;
use App\People\Application\Command\UpdateMyProfile\UpdateMyProfileHandler;
use App\People\Application\ContactView;
use App\People\Domain\PeopleException;
use App\People\Domain\Person;
use App\People\Domain\ValueObject\ContactKind;
use App\People\Domain\ValueObject\Gender;
use App\People\Domain\ValueObject\LifeStatus;
use App\People\Domain\ValueObject\MembershipType;
use App\People\Domain\ValueObject\PersonDetails;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Domain\CalendarDate;
use App\Shared\Domain\StaleRevisionException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** Self-service logic in isolation: who is acted on, in which order, and what a member may not change. */
final class MemberProfileHandlersTest extends TestCase
{
    private const string ACCOUNT = '11111111-1111-4111-8111-111111111111';
    private const string PERSON = '22222222-2222-4222-8222-222222222222';
    private const string OTHER_PERSON = '33333333-3333-4333-8333-333333333333';
    private const string CONTACT = '44444444-4444-4444-8444-444444444444';
    private const string SOMEONE_ELSES_CONTACT = '55555555-5555-4555-8555-555555555555';

    private InMemoryPersonRepository $people;
    private FakeMemberIdentity $identity;
    private RecordingAudit $audit;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-06-01 10:00:00 UTC');
        $this->audit = new RecordingAudit();
        $this->people = new InMemoryPersonRepository();
        $this->identity = new FakeMemberIdentity(self::ACCOUNT, self::PERSON);
        $details = new PersonDetails(null, 'Solly', 'Mabasa', CalendarDate::parse('1990-02-10'), Gender::Male, LifeStatus::Living, null);
        $this->people->insert(Person::register(self::PERSON, $details, MembershipType::ActiveMember, CalendarDate::parse('2026-06-01'), new DateTimeImmutable('2026-01-01T00:00:00Z')));
    }

    public function testEditingChangesOnlyNameTitleAndGenderOfTheVerifiedPerson(): void
    {
        $this->updateHandler()->handle(new UpdateMyProfileCommand(self::ACCOUNT, 'DR', 'Solly Updated', 'Mabasa', Gender::Female, 0));

        $person = $this->people->people[self::PERSON];
        self::assertSame('Solly Updated', $person->details->firstName);
        self::assertSame(Gender::Female, $person->details->gender);
        self::assertSame('1990-02-10', $person->details->dateOfBirth?->iso(), 'date of birth is staff-managed');
        self::assertSame(LifeStatus::Living, $person->details->lifeStatus);
        self::assertSame(MembershipType::ActiveMember, $person->membershipType, 'membership is staff-managed');
        self::assertSame(1, $person->revision->version);
        self::assertSame([self::ACCOUNT.':person.self_updated:'.self::PERSON], $this->audit->entries);
    }

    public function testTheCallerIsVerifiedBeforeAndAfterTheLockIsTaken(): void
    {
        $this->updateHandler()->handle(new UpdateMyProfileCommand(self::ACCOUNT, null, 'A', 'B', Gender::Male, 0));

        self::assertSame(['require', 'lock', 'require', 'require'], $this->identity->calls, 'lockPerson verifies internally, then the handler re-checks');
    }

    public function testASpoofedActorIsRefusedBeforeAnythingChanges(): void
    {
        $handlers = [
            fn () => $this->updateHandler()->handle(new UpdateMyProfileCommand(self::OTHER_PERSON, null, 'A', 'B', Gender::Male, 0)),
            fn () => $this->saveHandler()->handle(new SaveMyContactCommand(self::OTHER_PERSON, null, ContactKind::Email, 'a@b.org', 0)),
            fn () => $this->removeHandler()->handle(new RemoveMyContactCommand(self::OTHER_PERSON, self::CONTACT, 0)),
        ];
        foreach ($handlers as $run) {
            try {
                $run();
                self::fail('Expected a permission failure.');
            } catch (IdentityException) {
                self::assertSame(0, $this->people->people[self::PERSON]->revision->version);
                self::assertSame([], $this->audit->entries);
            }
        }
    }

    public function testAnUnverifiedMemberCannotEdit(): void
    {
        $this->identity = new FakeMemberIdentity(self::ACCOUNT, null);

        $this->expectException(IdentityException::class);
        $this->updateHandler()->handle(new UpdateMyProfileCommand(self::ACCOUNT, null, 'A', 'B', Gender::Male, 0));
    }

    public function testUnknownTitlesAndStaleVersionsAreRejected(): void
    {
        try {
            $this->updateHandler()->handle(new UpdateMyProfileCommand(self::ACCOUNT, 'NOPE', 'A', 'B', Gender::Male, 0));
            self::fail('Expected an invalid title.');
        } catch (PeopleException $error) {
            self::assertSame('Select an existing title.', $error->getMessage());
        }

        $this->expectException(StaleRevisionException::class);
        $this->updateHandler()->handle(new UpdateMyProfileCommand(self::ACCOUNT, null, 'A', 'B', Gender::Male, 4));
    }

    public function testSavingAContactAddsItAsPrimary(): void
    {
        $id = $this->saveHandler()->handle(new SaveMyContactCommand(self::ACCOUNT, null, ContactKind::Email, ' New@Example.org ', 0));

        self::assertSame($id, $this->people->contactsAdded[0]->id);
        self::assertSame('new@example.org', $this->people->contactsAdded[0]->value);
        self::assertTrue($this->people->contactsAdded[0]->primary);
        self::assertSame(self::PERSON, $this->people->contactsAdded[0]->personId);
        self::assertSame([], $this->people->contactsEnded);
    }

    public function testReplacingEndsTheOldContactOnlyWhenItIsYoursActiveAndOfTheSameKind(): void
    {
        $replace = static fn (string $contact, ContactKind $kind) => new SaveMyContactCommand(self::ACCOUNT, $contact, $kind, ContactKind::Email === $kind ? 'a@b.org' : '0821234567', 0);

        $this->saveHandler()->handle($replace(self::CONTACT, ContactKind::Phone));
        self::assertSame([self::CONTACT], $this->people->contactsEnded);

        foreach ([[self::SOMEONE_ELSES_CONTACT, ContactKind::Phone], [self::CONTACT, ContactKind::Email], ['66666666-6666-4666-8666-666666666666', ContactKind::Phone]] as [$contact, $kind]) {
            try {
                $this->saveHandler()->handle($replace($contact, $kind));
                self::fail('Expected the contact not to be found.');
            } catch (PeopleException $error) {
                self::assertSame('Contact was not found.', $error->getMessage());
            }
        }
        self::assertCount(1, $this->people->contactsAdded);
    }

    public function testRemovingEndsOnlyYourOwnActiveContact(): void
    {
        $this->removeHandler()->handle(new RemoveMyContactCommand(self::ACCOUNT, self::CONTACT, 0));
        self::assertSame([self::CONTACT], $this->people->contactsEnded);
        self::assertSame([self::ACCOUNT.':person.contact.self_removed:'.self::PERSON], $this->audit->entries);

        $this->expectExceptionMessage('Contact was not found.');
        $this->removeHandler()->handle(new RemoveMyContactCommand(self::ACCOUNT, self::SOMEONE_ELSES_CONTACT, 1));
    }

    public function testArchivedPeopleCannotChangeAnything(): void
    {
        $this->people->update($this->people->people[self::PERSON]->archive(true, 0, new DateTimeImmutable('2026-02-01T00:00:00Z')));

        $this->expectExceptionMessage('Restore the archived person before changing associations.');
        $this->saveHandler()->handle(new SaveMyContactCommand(self::ACCOUNT, null, ContactKind::Email, 'a@b.org', 1));
    }

    private function updateHandler(): UpdateMyProfileHandler
    {
        return new UpdateMyProfileHandler($this->identity, $this->identity, $this->people, new FixedTitles(['DR']), $this->audit, $this->clock, new ChurchCalendar($this->clock, 'Africa/Johannesburg'));
    }

    private function saveHandler(): SaveMyContactHandler
    {
        return new SaveMyContactHandler($this->identity, $this->identity, $this->people, $this->reader(), $this->audit, $this->clock, new SequentialIds());
    }

    private function removeHandler(): RemoveMyContactHandler
    {
        return new RemoveMyContactHandler($this->identity, $this->identity, $this->people, $this->reader(), $this->audit, $this->clock);
    }

    private function reader(): FakePersonReader
    {
        $at = new DateTimeImmutable('2026-01-01T00:00:00Z');

        return new FakePersonReader([
            new ContactView(self::CONTACT, self::PERSON, ContactKind::Phone, '0829999999', true, $at, null),
            new ContactView(self::SOMEONE_ELSES_CONTACT, self::OTHER_PERSON, ContactKind::Phone, '0828888888', true, $at, null),
        ]);
    }
}
