<?php

declare(strict_types=1);

namespace App\Tests\People\Presentation;

final class MemberProfileApiTest extends PeopleApiTestCase
{
    private string $memberId;
    private string $member;
    private string $person;

    protected function setUp(): void
    {
        parent::setUp();
        $this->memberId = $this->account('member');
        $this->member = $this->login('member');
        $this->person = $this->register('Solly');
    }

    private function link(bool $active, int $version): void
    {
        $this->call('PUT', "/api/member-access/{$this->memberId}", $this->admin, [
            'personId' => $this->person, 'active' => $active, 'expectedVersion' => $version, 'evidence' => 'Account holder verified in person',
        ]);
        self::assertSame(204, $this->code());
    }

    /** @return array<string|int, mixed> */
    private function profile(): array
    {
        $this->call('GET', '/api/member/profile', $this->member);
        self::assertSame(200, $this->code());

        return $this->json();
    }

    public function testMemberIdentityIsExplicitAndGrantsNoDirectoryOrStaffAccess(): void
    {
        $this->call('GET', '/api/member/profile');
        self::assertSame(401, $this->code());

        self::assertSame(['profile' => null, 'titles' => []], $this->profile());
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $this->link(true, 0);
        $profile = $this->profile()['profile'];
        self::assertSame($this->person, $profile['id']);
        self::assertSame('Solly', $profile['details']['firstName']);
        self::assertSame('VISITOR', $profile['membershipType']);
        self::assertSame(0, $profile['version']);
        self::assertNotEmpty($this->profile()['titles']);

        $this->call('GET', '/api/people', $this->member);
        self::assertSame(403, $this->code(), 'linking grants no directory access');
        $this->call('PUT', "/api/member-access/{$this->memberId}", $this->member, ['personId' => $this->person, 'active' => true, 'expectedVersion' => 1, 'evidence' => 'Self-claim']);
        self::assertSame(403, $this->code(), 'members cannot link themselves');
    }

    public function testSelfEditsPreserveStaffManagedFieldsAndIgnoreInjectedOnes(): void
    {
        $this->connection->executeStatement("UPDATE people_persons SET date_of_birth = '1990-02-10' WHERE id = ?", [$this->person]);
        $this->link(true, 0);

        $this->call('PUT', '/api/member/profile', $this->member, [
            'titleCode' => 'MR', 'firstName' => 'Solly Updated', 'lastName' => 'Mabasa', 'gender' => 'MALE', 'expectedVersion' => 0,
            'dateOfBirth' => '2018-01-01', 'membershipType' => 'TRANSFERRED', 'personId' => '00000000-0000-4000-8000-0000000000aa',
        ]);
        self::assertSame(204, $this->code());

        self::assertSame('1990-02-10', (string) $this->connection->fetchOne('SELECT date_of_birth FROM people_persons WHERE id = ?', [$this->person]));
        $profile = $this->profile()['profile'];
        self::assertSame(['Solly Updated', 'MR', 'VISITOR'], [$profile['details']['firstName'], $profile['details']['titleCode'], $profile['membershipType']]);
        self::assertSame(1, $this->rows('people_audit', "action = 'person.self_updated' AND target_reference = '{$this->person}' AND actor_id = '{$this->memberId}'"));

        $this->call('PUT', '/api/member/profile', $this->member, ['firstName' => 'Stale', 'lastName' => 'Name', 'gender' => 'UNKNOWN', 'expectedVersion' => 0]);
        self::assertSame(409, $this->code());
    }

    public function testProfileInputIsValidated(): void
    {
        $this->link(true, 0);
        $valid = ['firstName' => 'A', 'lastName' => 'B', 'gender' => 'MALE', 'expectedVersion' => 0];
        $bad = [
            'missing version' => array_diff_key($valid, ['expectedVersion' => 1]),
            'missing gender' => array_diff_key($valid, ['gender' => 1]),
            'unknown gender' => ['gender' => 'ROBOT'] + $valid,
            'blank name' => ['firstName' => ' '] + $valid,
            'long name' => ['lastName' => str_repeat('x', 101)] + $valid,
            'unknown title' => ['titleCode' => 'NOPE'] + $valid,
        ];
        foreach ($bad as $name => $body) {
            $this->call('PUT', '/api/member/profile', $this->member, $body);
            self::assertSame(400, $this->code(), $name);
        }
        self::assertSame(0, $this->version('people_persons', $this->person));
    }

    public function testContactsAreOwnedVersionedAndReplacedWithoutErasingHistory(): void
    {
        $this->link(true, 0);

        $this->call('PUT', '/api/member/profile/contacts', $this->member, ['kind' => 'EMAIL', 'value' => 'first@example.org', 'expectedVersion' => 0]);
        self::assertSame(201, $this->code());
        $first = (string) $this->json()['id'];
        $this->call('PUT', '/api/member/profile/contacts', $this->member, ['contactId' => $first, 'kind' => 'EMAIL', 'value' => 'Second@Example.org', 'expectedVersion' => 1]);
        self::assertSame(201, $this->code());
        $second = (string) $this->json()['id'];

        self::assertSame(2, $this->rows('people_contact_methods', "person_id = '{$this->person}'"));
        self::assertSame(1, $this->rows('people_contact_methods', "id = '$first' AND ended_at IS NOT NULL AND NOT is_primary"));
        self::assertSame(['second@example.org'], array_column($this->profile()['profile']['contacts'], 'value'), 'only active contacts are shown to the member');

        $other = $this->register('Another');
        $this->call('POST', "/api/people/$other/contacts", $this->admin, ['expectedVersion' => 0, 'kind' => 'EMAIL', 'value' => 'other@example.org', 'primary' => true]);
        $otherContact = (string) $this->json()['id'];

        $this->call('DELETE', "/api/member/profile/contacts/$otherContact?expectedVersion=2", $this->member);
        self::assertSame(404, $this->code());
        $this->call('PUT', '/api/member/profile/contacts', $this->member, ['contactId' => $otherContact, 'kind' => 'EMAIL', 'value' => 'steal@example.org', 'expectedVersion' => 2]);
        self::assertSame(404, $this->code());
        $this->call('PUT', '/api/member/profile/contacts', $this->member, ['contactId' => $first, 'kind' => 'EMAIL', 'value' => 'again@example.org', 'expectedVersion' => 2]);
        self::assertSame(404, $this->code(), 'an ended contact cannot be replaced again');
        $this->call('PUT', '/api/member/profile/contacts', $this->member, ['contactId' => $second, 'kind' => 'PHONE', 'value' => '0821234567', 'expectedVersion' => 2]);
        self::assertSame(404, $this->code(), 'replacement must be of the same kind');
        self::assertSame(2, $this->version('people_persons', $this->person), 'failed commands leave no trace');

        $this->call('DELETE', "/api/member/profile/contacts/$second?expectedVersion=1", $this->member);
        self::assertSame(409, $this->code(), 'stale version');
        $this->call('DELETE', "/api/member/profile/contacts/$second?expectedVersion=2", $this->member);
        self::assertSame(204, $this->code());
        self::assertSame([], $this->profile()['profile']['contacts']);
        self::assertSame(1, $this->rows('people_audit', "action = 'person.contact.self_removed'"));
    }

    public function testContactInputIsValidated(): void
    {
        $this->link(true, 0);
        $bad = [
            'missing kind' => ['value' => 'a@b.org', 'expectedVersion' => 0],
            'unknown kind' => ['kind' => 'FAX', 'value' => '123', 'expectedVersion' => 0],
            'bad email' => ['kind' => 'EMAIL', 'value' => 'not an email', 'expectedVersion' => 0],
            'bad phone' => ['kind' => 'PHONE', 'value' => 'call me', 'expectedVersion' => 0],
            'missing version' => ['kind' => 'EMAIL', 'value' => 'a@b.org'],
            'bad contact id' => ['contactId' => 'nope', 'kind' => 'EMAIL', 'value' => 'a@b.org', 'expectedVersion' => 0],
        ];
        foreach ($bad as $name => $body) {
            $this->call('PUT', '/api/member/profile/contacts', $this->member, $body);
            self::assertSame(400, $this->code(), $name);
        }
        $this->call('DELETE', '/api/member/profile/contacts/00000000-0000-4000-8000-0000000000aa', $this->member);
        self::assertSame(400, $this->code(), 'expectedVersion is required');
        self::assertSame(0, $this->rows('people_contact_methods'));
    }

    public function testRevokedOrUnverifiedAccountsAreShutOutUntilStaffRestoreAccess(): void
    {
        $edit = ['firstName' => 'Wrong', 'lastName' => 'Person', 'gender' => 'UNKNOWN', 'expectedVersion' => 0];
        $this->call('PUT', '/api/member/profile', $this->member, $edit);
        self::assertSame(403, $this->code(), 'never verified');

        $this->link(true, 0);
        $this->link(false, 1);
        self::assertNull($this->profile()['profile']);
        $this->call('PUT', '/api/member/profile', $this->member, $edit);
        self::assertSame(403, $this->code());
        $this->call('PUT', '/api/member/profile/contacts', $this->member, ['kind' => 'EMAIL', 'value' => 'a@b.org', 'expectedVersion' => 0]);
        self::assertSame(403, $this->code());
        self::assertSame(0, $this->rows('people_contact_methods'));

        $this->link(true, 2);
        self::assertNotNull($this->profile()['profile']);
    }

    public function testArchivedPeopleAppearUnverifiedAndCannotEdit(): void
    {
        $this->link(true, 0);
        $this->call('PUT', "/api/people/{$this->person}/archive", $this->admin, ['expectedVersion' => 0, 'archived' => true]);

        self::assertNull($this->profile()['profile']);
        $this->call('PUT', '/api/member/profile', $this->member, ['firstName' => 'A', 'lastName' => 'B', 'gender' => 'MALE', 'expectedVersion' => 1]);
        self::assertSame(403, $this->code());
    }

    public function testEachMemberOnlyEverSeesAndEditsTheirOwnPerson(): void
    {
        $this->link(true, 0);
        $otherAccount = $this->account('neighbour');
        $otherPerson = $this->register('Neighbour');
        $this->call('PUT', "/api/member-access/$otherAccount", $this->admin, ['personId' => $otherPerson, 'active' => true, 'expectedVersion' => 0, 'evidence' => 'Verified']);
        $neighbour = $this->login('neighbour');

        $this->call('PUT', '/api/member/profile', $neighbour, ['firstName' => 'Mine', 'lastName' => 'Only', 'gender' => 'FEMALE', 'expectedVersion' => 0]);
        self::assertSame(204, $this->code());

        self::assertSame('Solly', $this->profile()['profile']['details']['firstName']);
        self::assertSame('Mine', $this->person($otherPerson)['details']['firstName']);
    }
}
