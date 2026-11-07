<?php

declare(strict_types=1);

namespace App\Tests\Events\Presentation;

use App\Tests\Support\IdentityApiTestCase;

final class EventVenuesApiTest extends IdentityApiTestCase
{
    private string $manager;
    private string $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountWith('manager', ['events.manage', 'events.read']);
        $this->accountWith('reader', ['events.read']);
        $this->manager = $this->login('manager');
        $this->reader = $this->login('reader');
    }

    private function venue(string $name = 'Main hall', bool $exclusive = true): string
    {
        $this->call('POST', '/api/events/venues', $this->manager, ['name' => $name, 'address' => '1 Church St', 'exclusive' => $exclusive]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    public function testVenuesAreCreatedUpdatedAndListed(): void
    {
        $id = $this->venue();
        self::assertSame(1, $this->rows('event_audit', "action = 'venue.created' AND target_id = '$id'"));

        $this->call('PUT', "/api/events/venues/$id", $this->manager, ['expectedVersion' => 0, 'name' => 'Annex', 'address' => '', 'active' => true]);
        self::assertSame(204, $this->code());
        self::assertSame(1, $this->rows('event_audit', "action = 'venue.updated' AND target_id = '$id'"));

        $this->call('GET', '/api/events/venues', $this->reader);
        self::assertSame(200, $this->code());
        self::assertSame(1, $this->json()['total']);
        self::assertSame(
            ['id' => $id, 'name' => 'Annex', 'address' => '', 'exclusive' => true, 'active' => true, 'version' => 1],
            $this->json()['venues'][0],
        );
    }

    public function testArchivedVenuesAreHiddenUnlessRequestedAndSearchIsLiteral(): void
    {
        $id = $this->venue('Hall_A');
        $this->venue('Hall B');
        $this->call('PUT', "/api/events/venues/$id", $this->manager, ['expectedVersion' => 0, 'name' => 'Hall_A', 'address' => '', 'active' => false]);

        $this->call('GET', '/api/events/venues', $this->reader);
        self::assertSame(['Hall B'], array_column($this->json()['venues'], 'name'));
        $this->call('GET', '/api/events/venues?includeArchived=true', $this->reader);
        self::assertSame(2, $this->json()['total']);
        $this->call('GET', '/api/events/venues?includeArchived=true&search=_', $this->reader);
        self::assertSame(['Hall_A'], array_column($this->json()['venues'], 'name'));
        $this->call('GET', '/api/events/venues?limit=1&offset=1&includeArchived=true', $this->reader);
        self::assertSame(2, $this->json()['total']);
        self::assertCount(1, $this->json()['venues']);
    }

    public function testStaleVersionIsAConflictAndChangesNothing(): void
    {
        $id = $this->venue();
        $this->call('PUT', "/api/events/venues/$id", $this->manager, ['expectedVersion' => 5, 'name' => 'X', 'address' => '', 'active' => true]);

        self::assertSame(409, $this->code());
        self::assertSame(0, $this->rows('event_audit', "action = 'venue.updated'"));
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT version FROM event_venues WHERE id = ?', [$id]));
    }

    public function testMalformedInputIsRejected(): void
    {
        $this->call('POST', '/api/events/venues', $this->manager, ['name' => ' ']);
        self::assertSame(400, $this->code());
        $id = $this->venue();
        $this->call('PUT', "/api/events/venues/$id", $this->manager, ['name' => 'X', 'active' => true]);
        self::assertSame(400, $this->code(), 'expectedVersion is required');
        $this->call('PUT', '/api/events/venues/not-a-uuid', $this->manager, ['expectedVersion' => 0, 'name' => 'X']);
        self::assertSame(400, $this->code());
        foreach (['limit=0', 'limit=101', 'limit=abc', 'offset=-1', 'offset=100001'] as $query) {
            $this->call('GET', "/api/events/venues?$query", $this->reader);
            self::assertSame(400, $this->code(), $query);
        }
    }

    public function testUnknownVenueIsNotFound(): void
    {
        $this->call('PUT', '/api/events/venues/00000000-0000-4000-8000-0000000000aa', $this->manager, ['expectedVersion' => 0, 'name' => 'X', 'address' => '', 'active' => true]);
        self::assertSame(404, $this->code());
    }

    public function testPermissionsAreEnforced(): void
    {
        $this->call('GET', '/api/events/venues');
        self::assertSame(401, $this->code());
        $this->call('POST', '/api/events/venues', null, ['name' => 'X']);
        self::assertSame(401, $this->code());

        $this->call('POST', '/api/events/venues', $this->reader, ['name' => 'X']);
        self::assertSame(403, $this->code());
        $this->call('PUT', '/api/events/venues/00000000-0000-4000-8000-0000000000aa', $this->reader, ['expectedVersion' => 0, 'name' => 'X']);
        self::assertSame(403, $this->code());

        self::assertSame(0, $this->rows('event_venues'));
    }

    public function testManagingImpliesReading(): void
    {
        $this->accountWith('writer', ['events.manage']);
        $this->call('GET', '/api/events/venues', $this->login('writer'));
        self::assertSame(200, $this->code());
    }
}
