<?php

declare(strict_types=1);

namespace App\Tests\People\Presentation;

final class HouseholdApiTest extends PeopleApiTestCase
{
    private function household(string $name = 'Mabasa family'): string
    {
        $this->call('POST', '/api/households', $this->admin, ['name' => $name]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    private function join(string $household, string $person, int $version, bool $primary = false): string
    {
        $this->call('POST', "/api/households/$household/members", $this->admin, ['expectedVersion' => $version, 'personId' => $person, 'primary' => $primary]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    public function testHouseholdsAreCreatedRenamedAndSearched(): void
    {
        $id = $this->household();

        $this->call('PUT', "/api/households/$id", $this->admin, ['expectedVersion' => 0, 'name' => 'Mabasa']);
        self::assertSame(204, $this->code());
        $this->call('PUT', "/api/households/$id", $this->admin, ['expectedVersion' => 0, 'name' => 'Stale']);
        self::assertSame(409, $this->code());
        $this->call('PUT', "/api/households/$id", $this->admin, ['expectedVersion' => 1, 'name' => ' ']);
        self::assertSame(400, $this->code());

        $this->call('GET', "/api/households/$id", $this->reader);
        self::assertSame('Mabasa', $this->json()['household']['name']);
        self::assertSame(1, $this->json()['household']['version']);
        self::assertSame([], $this->json()['memberships']);

        $this->household('Dlamini_family');
        $this->call('GET', '/api/households?search=MABA', $this->reader);
        self::assertSame(['Mabasa'], array_column($this->json(), 'name'));
        $this->call('GET', '/api/households?search=_', $this->reader);
        self::assertSame(['Dlamini_family'], array_column($this->json(), 'name'), 'underscore is literal');
        $this->call('GET', '/api/households?limit=0', $this->reader);
        self::assertSame(400, $this->code());
        self::assertSame(1, $this->rows('people_audit', "action = 'household.updated' AND target_reference = '$id'"));
    }

    public function testMembershipsAreDatedAndPeopleCanBelongToSeveralHouseholds(): void
    {
        $person = $this->register('Solly');
        $first = $this->household('First');
        $second = $this->household('Second');

        $membership = $this->join($first, $person, 0, true);
        $this->join($second, $person, 0);

        $this->call('GET', "/api/people/$person/households", $this->reader);
        self::assertCount(2, $this->json());
        self::assertSame($person, $this->json()[0]['personId']);
        self::assertNull($this->json()[0]['validTo']);

        $this->call('POST', "/api/households/$first/members/$membership/end", $this->admin, ['expectedVersion' => 1]);
        self::assertSame(204, $this->code());
        $this->call('GET', "/api/households/$first", $this->reader);
        self::assertNotNull($this->json()['memberships'][0]['validTo']);

        $this->call('POST', "/api/households/$first/members/$membership/end", $this->admin, ['expectedVersion' => 2]);
        self::assertSame(409, $this->code(), 'already ended');
        self::assertSame(2, $this->version('people_households', $first), 'a failed end changes nothing');
    }

    public function testOnlyOnePrimaryHouseholdAtATimeAndNoDuplicateCurrentMembership(): void
    {
        $person = $this->register('Solly');
        $first = $this->household('First');
        $second = $this->household('Second');
        $this->join($first, $person, 0, true);

        $this->call('POST', "/api/households/$second/members", $this->admin, ['expectedVersion' => 0, 'personId' => $person, 'primary' => true]);
        self::assertSame(409, $this->code());
        $this->call('POST', "/api/households/$first/members", $this->admin, ['expectedVersion' => 1, 'personId' => $person]);
        self::assertSame(409, $this->code());
        self::assertSame(1, $this->rows('people_household_memberships'));
    }

    public function testPrimaryContactMustBeACurrentMemberAndClearsWhenTheyLeave(): void
    {
        $solly = $this->register('Solly');
        $sarah = $this->register('Sarah');
        $household = $this->household();
        $other = $this->household('Other');
        $membership = $this->join($household, $solly, 0);
        $foreign = $this->join($other, $sarah, 0);

        $this->call('PUT', "/api/households/$household/primary-contact", $this->admin, ['expectedVersion' => 1, 'membershipId' => $membership]);
        self::assertSame(204, $this->code());
        $this->call('GET', "/api/households/$household", $this->admin);
        self::assertSame($membership, $this->json()['household']['primaryContactMembershipId']);

        $this->call('PUT', "/api/households/$household/primary-contact", $this->admin, ['expectedVersion' => 2, 'membershipId' => $foreign]);
        self::assertSame(404, $this->code(), 'a membership of another household is not found here');
        $this->call('PUT', "/api/households/$household/primary-contact", $this->admin, ['expectedVersion' => 2, 'membershipId' => 'nope']);
        self::assertSame(400, $this->code());

        $this->call('POST', "/api/households/$household/members/$membership/end", $this->admin, ['expectedVersion' => 2]);
        self::assertSame(204, $this->code());
        $this->call('GET', "/api/households/$household", $this->admin);
        self::assertNull($this->json()['household']['primaryContactMembershipId']);

        $this->call('PUT', "/api/households/$household/primary-contact", $this->admin, ['expectedVersion' => 3]);
        self::assertSame(204, $this->code(), 'null clears the contact');
    }

    public function testArchivedAndUnknownPeopleCannotJoin(): void
    {
        $person = $this->register('Solly');
        $household = $this->household();
        $this->call('PUT', "/api/people/$person/archive", $this->admin, ['expectedVersion' => 0, 'archived' => true]);

        $this->call('POST', "/api/households/$household/members", $this->admin, ['expectedVersion' => 0, 'personId' => $person]);
        self::assertSame(409, $this->code());
        $this->call('POST', "/api/households/$household/members", $this->admin, ['expectedVersion' => 0, 'personId' => '00000000-0000-4000-8000-0000000000aa']);
        self::assertSame(404, $this->code());
        $this->call('POST', "/api/households/$household/members", $this->admin, ['expectedVersion' => 0]);
        self::assertSame(400, $this->code());
        $this->call('GET', '/api/households/00000000-0000-4000-8000-0000000000aa', $this->admin);
        self::assertSame(404, $this->code());
    }
}
