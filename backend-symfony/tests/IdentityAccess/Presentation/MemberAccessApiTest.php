<?php

declare(strict_types=1);

namespace App\Tests\IdentityAccess\Presentation;

use App\Tests\Support\IdentityApiTestCase;

final class MemberAccessApiTest extends IdentityApiTestCase
{
    private string $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account('admin', administrator: true);
        $this->admin = $this->login('admin');
    }

    private function person(string $firstName = 'Solly'): string
    {
        $this->call('POST', '/api/people', $this->admin, ['person' => ['firstName' => $firstName, 'lastName' => 'Mabasa']]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    /** @return array<string, mixed> */
    private function body(string $person, bool $active, int $version, string $evidence = 'Checked ID at the welcome desk'): array
    {
        return ['personId' => $person, 'active' => $active, 'expectedVersion' => $version, 'evidence' => $evidence];
    }

    public function testStaffLinkAnAccountToAPersonAndSeeItListedWithNames(): void
    {
        $member = $this->account('member');
        $person = $this->person();

        $this->call('PUT', "/api/member-access/$member", $this->admin, $this->body($person, true, 0));
        self::assertSame(204, $this->code());

        $this->call('GET', '/api/member-access', $this->admin);
        self::assertSame(200, $this->code());
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertFalse($this->json()['hasMore']);
        self::assertSame(
            ['accountId' => $member, 'accountName' => 'Member', 'personId' => $person, 'personName' => 'Solly Mabasa', 'active' => true, 'version' => 1],
            $this->json()['links'][0],
        );
        self::assertSame(1, $this->rows('identity_member_link_history', "account_id = '$member' AND version = 1 AND evidence = 'Checked ID at the welcome desk'"));
    }

    public function testRevokingKeepsHistoryAndTheSamePersonCanBeRestoredButNeverSwapped(): void
    {
        $member = $this->account('member');
        $person = $this->person();
        $other = $this->person('Sarah');
        $this->call('PUT', "/api/member-access/$member", $this->admin, $this->body($person, true, 0));

        $this->call('PUT', "/api/member-access/$member", $this->admin, $this->body($person, false, 1, 'Left the church'));
        self::assertSame(204, $this->code());
        $this->call('PUT', "/api/member-access/$member", $this->admin, $this->body($person, true, 1));
        self::assertSame(409, $this->code(), 'stale version');
        $this->call('PUT', "/api/member-access/$member", $this->admin, $this->body($other, true, 2));
        self::assertSame(409, $this->code(), 'a verified identity cannot be reassigned');
        $this->call('PUT', "/api/member-access/$member", $this->admin, $this->body($person, true, 2, 'Returned'));
        self::assertSame(204, $this->code());

        self::assertSame(3, $this->rows('identity_member_link_history', "account_id = '$member'"));
        $this->call('GET', '/api/member-access', $this->admin);
        self::assertSame([true, 3], [$this->json()['links'][0]['active'], $this->json()['links'][0]['version']]);
    }

    public function testHistoryRowsCannotBeAlteredOrDeleted(): void
    {
        $member = $this->account('member');
        $this->call('PUT', "/api/member-access/$member", $this->admin, $this->body($this->person(), true, 0));

        $this->expectException(\Doctrine\DBAL\Exception::class);
        $this->connection->executeStatement('DELETE FROM identity_member_link_history');
    }

    public function testOnePersonCannotBeLinkedToTwoAccounts(): void
    {
        $first = $this->account('first');
        $second = $this->account('second');
        $person = $this->person();
        $this->call('PUT', "/api/member-access/$first", $this->admin, $this->body($person, true, 0));

        $this->call('PUT', "/api/member-access/$second", $this->admin, $this->body($person, true, 0));

        self::assertSame(409, $this->code());
        self::assertSame(1, $this->rows('identity_member_links'));
    }

    public function testNewLinksNeedAnActiveAccountAnAvailablePersonAndAFreshVersion(): void
    {
        $disabled = $this->account('gone', status: 'disabled');
        $member = $this->account('member');
        $person = $this->person();
        $archived = $this->person('Old');
        $this->call('PUT', "/api/people/$archived/archive", $this->admin, ['expectedVersion' => 0, 'archived' => true]);

        $this->call('PUT', "/api/member-access/$disabled", $this->admin, $this->body($person, true, 0));
        self::assertSame(409, $this->code());
        $this->call('PUT', "/api/member-access/$member", $this->admin, $this->body($archived, true, 0));
        self::assertSame(409, $this->code());
        $this->call('PUT', "/api/member-access/$member", $this->admin, $this->body($person, false, 0));
        self::assertSame(409, $this->code(), 'cannot start with a revocation');
        $this->call('PUT', "/api/member-access/$member", $this->admin, $this->body($person, true, 3));
        self::assertSame(409, $this->code());
        $this->call('PUT', '/api/member-access/00000000-0000-4000-8000-0000000000aa', $this->admin, $this->body($person, true, 0));
        self::assertSame(404, $this->code(), 'unknown account');
        self::assertSame(0, $this->rows('identity_member_links'));
    }

    public function testMalformedRequestsAreRejected(): void
    {
        $member = $this->account('member');
        $person = $this->person();
        $bad = [
            'blank evidence' => $this->body($person, true, 0, '  '),
            'long evidence' => $this->body($person, true, 0, str_repeat('x', 1001)),
            'missing version' => ['personId' => $person, 'active' => true, 'evidence' => 'x'],
            'negative version' => $this->body($person, true, -1),
            'missing person' => ['active' => true, 'expectedVersion' => 0, 'evidence' => 'x'],
            'bad person id' => $this->body('nope', true, 0),
        ];
        foreach ($bad as $name => $body) {
            $this->call('PUT', "/api/member-access/$member", $this->admin, $body);
            self::assertSame(400, $this->code(), $name);
        }
        $this->call('PUT', '/api/member-access/not-a-uuid', $this->admin, $this->body($person, true, 0));
        self::assertSame(400, $this->code());
    }

    public function testBothStaffPermissionsAreNeededAndAnonymousCallersAreRejected(): void
    {
        $member = $this->account('member');
        $person = $this->person();
        $this->accountWith('identity-only', ['identity.manage']);
        $this->accountWith('people-only', ['people.manage']);
        $this->account('plain');

        foreach (['identity-only', 'people-only', 'plain'] as $username) {
            $token = $this->login($username);
            $this->call('GET', '/api/member-access', $token);
            self::assertSame(403, $this->code(), "$username list");
            $this->call('PUT', "/api/member-access/$member", $token, $this->body($person, true, 0));
            self::assertSame(403, $this->code(), "$username verify");
        }
        $this->call('GET', '/api/member-access');
        self::assertSame(401, $this->code());
        self::assertSame(0, $this->rows('identity_member_links'));
    }

    public function testListingIsPagedWithAHasMoreFlag(): void
    {
        foreach (['amy', 'ben', 'cia'] as $name) {
            $account = $this->account($name);
            $this->call('PUT', "/api/member-access/$account", $this->admin, $this->body($this->person(strtoupper($name)), true, 0));
            self::assertSame(204, $this->code(), $name);
        }

        $this->call('GET', '/api/member-access?limit=2', $this->admin);
        self::assertCount(2, $this->json()['links']);
        self::assertTrue($this->json()['hasMore']);
        $this->call('GET', '/api/member-access?limit=2&offset=2', $this->admin);
        self::assertCount(1, $this->json()['links']);
        self::assertFalse($this->json()['hasMore']);

        foreach (['limit=0', 'limit=101', 'offset=-1', 'limit=abc'] as $query) {
            $this->call('GET', "/api/member-access?$query", $this->admin);
            self::assertSame(400, $this->code(), $query);
        }
    }
}
