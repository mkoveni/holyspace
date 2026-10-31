<?php

declare(strict_types=1);

namespace App\Tests\People\Infrastructure\ReadModel;

use App\People\Infrastructure\ReadModel\DBAL\DbalFamilyOverviewRepository;
use Doctrine\DBAL\DriverManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class FamilyOverviewTest extends KernelTestCase
{
    public function testOverviewIncludesContactsAddressesAndDistinctUpcomingEvents(): void
    {
        $db = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $db->executeStatement('CREATE TABLE people_families (id TEXT, name TEXT, created_at TEXT, updated_at TEXT, primary_contact_person_id TEXT)');
        $db->executeStatement('CREATE TABLE people_persons (id TEXT, first_name TEXT, last_name TEXT, date_of_birth TEXT, gender TEXT, email TEXT, phone TEXT)');
        $db->executeStatement('CREATE TABLE people_family_memberships (family_id TEXT, person_id TEXT, role TEXT, created_at TEXT)');
        $db->executeStatement('CREATE TABLE people_family_addresses (id TEXT, family_id TEXT, type TEXT, line1 TEXT, line2 TEXT, city TEXT, province TEXT, postal_code TEXT, country TEXT, created_at TEXT)');
        $db->executeStatement('CREATE TABLE life_events (id TEXT, type TEXT, event_date TEXT, notes TEXT, status TEXT)');
        $db->executeStatement('CREATE TABLE life_event_participants (life_event_id TEXT, person_id TEXT)');
        foreach (['f1' => 'Doe', 'f2' => 'Smith', 'f3' => 'Empty'] as $id => $name) {
            $db->insert('people_families', ['id' => $id, 'name' => $name, 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01', 'primary_contact_person_id' => 'f1' === $id ? 'p1' : null]);
        }
        foreach (['p1' => 'Jane', 'p2' => 'John', 'p3' => 'Other'] as $id => $name) {
            $db->insert('people_persons', ['id' => $id, 'first_name' => $name, 'last_name' => 'Doe', 'gender' => 'unspecified', 'email' => $id.'@example.com', 'phone' => '+27123456789']);
        }
        foreach ([['f1', 'p1', 'head'], ['f1', 'p2', 'child'], ['f2', 'p2', 'other']] as [$family, $person, $role]) {
            $db->insert('people_family_memberships', ['family_id' => $family, 'person_id' => $person, 'role' => $role, 'created_at' => '2026-01-01']);
        }
        $db->insert('people_family_addresses', ['id' => 'a1', 'family_id' => 'f1', 'type' => 'residential', 'line1' => '12 Main Road', 'city' => 'Cape Town', 'postal_code' => '8000', 'country' => 'South Africa', 'created_at' => '2026-01-01']);
        foreach ([['future', '2026-11-01', 'active'], ['today', '2026-10-03', 'active'], ['past', '2026-10-02', 'active'], ['cancelled', '2026-11-01', 'cancelled'], ['completed', '2026-11-01', 'completed'], ['unrelated', '2026-11-01', 'active']] as [$id, $date, $status]) {
            $db->insert('life_events', ['id' => $id, 'type' => 'baptism', 'event_date' => $date, 'status' => $status]);
            $db->insert('life_event_participants', ['life_event_id' => $id, 'person_id' => 'unrelated' === $id ? 'p3' : 'p1']);
        }
        $db->insert('life_event_participants', ['life_event_id' => 'future', 'person_id' => 'p2']);
        $clock = new MockClock('2026-10-03 12:00:00');
        $read = new DbalFamilyOverviewRepository($db, $clock);
        $families = $read->overview();
        self::assertCount(3, $families);
        $family = $read->overview('f1')[0];
        self::assertCount(2, $family->members);
        self::assertSame(['head', 'child'], array_column($family->members, 'role'));
        self::assertSame('p1', $family->primaryContact->id);
        self::assertSame('p1@example.com', $family->primaryContact->email);
        self::assertSame('8000', $family->addresses[0]->postalCode);
        self::assertSame(['today', 'future'], array_column($family->upcomingEvents, 'id'));
        self::assertEqualsCanonicalizing(['p1', 'p2'], $family->upcomingEvents[1]->memberIds);
        self::assertCount(1, $read->overview('f2')[0]->upcomingEvents);
        self::assertNull($read->overview('f2')[0]->primaryContact);
        self::assertSame([], $read->overview('f3')[0]->members);
        self::assertSame([], $read->overview('missing'));
        foreach ([['wedding', 'marriage', '2010-10-03', 'completed'], ['birthday', 'birth', '2000-10-04', 'active'], ['engagement', 'engagement', '2020-01-01', 'completed'], ['leap', 'birth', '2000-02-29', 'completed'], ['cancelled-wedding', 'marriage', '2010-10-04', 'cancelled'], ['unrelated-wedding', 'marriage', '2010-10-04', 'active']] as [$id, $type, $date, $status]) {
            $db->insert('life_events', ['id' => $id, 'type' => $type, 'event_date' => $date, 'status' => $status]);
            $db->insert('life_event_participants', ['life_event_id' => $id, 'person_id' => 'unrelated-wedding' === $id ? 'p3' : 'p1']);
        }
        $db->insert('life_event_participants', ['life_event_id' => 'wedding', 'person_id' => 'p2']);
        $events = $read->overview('f1')[0]->upcomingEvents;
        self::assertSame(['today', 'wedding', 'birthday', 'future', 'engagement', 'leap'], array_column($events, 'id'));
        self::assertSame(['2026-10-03', '2026-10-03', '2026-10-04', '2026-11-01', '2027-01-01', '2027-02-28'], array_column($events, 'eventDate'));
        self::assertFalse($events[0]->isAnniversary);
        self::assertTrue($events[1]->isAnniversary);
        self::assertSame('2010-10-03', $events[1]->originalEventDate);
        self::assertEqualsCanonicalizing(['p1', 'p2'], $events[1]->memberIds);
        self::assertCount(2, $read->overview('f2')[0]->upcomingEvents);
        $clock->modify('+1 day');
        $events = $read->overview('f1')[0]->upcomingEvents;
        self::assertSame('birthday', $events[0]->id);
        self::assertSame('2027-10-03', $events[array_key_last($events)]->eventDate);
        $clock->modify('2028-02-28');
        $events = $read->overview('f1')[0]->upcomingEvents;
        self::assertSame('leap', $events[0]->id);
        self::assertSame('2028-02-29', $events[0]->eventDate);
    }
}
