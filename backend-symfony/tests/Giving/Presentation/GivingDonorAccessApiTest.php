<?php

declare(strict_types=1);

namespace App\Tests\Giving\Presentation;

use App\Tests\Support\IdentityApiTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * The Giving-side cases of Java's DonorGivingApiIntegrationTest and MemberProfileApiIntegrationTest (the Payments
 * self-service parts wait for Payments), plus the shape, permission and malformed-input checks.
 */
final class GivingDonorAccessApiTest extends IdentityApiTestCase
{
    private string $admin;
    private string $donorAccount;
    private string $donor;
    private string $fund;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account('admin', administrator: true);
        $this->admin = $this->login('admin');
        $this->donorAccount = $this->account('donor');
        $this->donor = $this->person('Donor');
        $this->call('POST', '/api/giving/funds', $this->admin, ['requestId' => Uuid::v4()->toRfc4122(), 'name' => 'General']);
        self::assertSame(201, $this->code(), $this->message());
        $this->fund = (string) $this->json()['id'];
    }

    private function person(string $firstName): string
    {
        $this->call('POST', '/api/people', $this->admin, ['person' => ['firstName' => $firstName, 'lastName' => 'Mabasa']]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    /** @return array<string, mixed> */
    private static function body(string $person, bool $active, int $version, string $evidence = 'Verified account holder identity in person'): array
    {
        return ['personId' => $person, 'active' => $active, 'expectedVersion' => $version, 'evidence' => $evidence];
    }

    private function put(string $account, string $person, bool $active, int $version, ?string $token = null): void
    {
        $this->call('PUT', "/api/giving/donor-access/$account", $token ?? $this->admin, self::body($person, $active, $version));
    }

    private function link(bool $active, int $version): void
    {
        $this->put($this->donorAccount, $this->donor, $active, $version);
        self::assertSame(204, $this->code(), (string) $this->client->getResponse()->getContent());
    }

    private function offer(bool $enabled, int $version): void
    {
        $this->call('PUT', "/api/giving/online-funds/{$this->fund}", $this->admin, ['enabled' => $enabled, 'expectedVersion' => $version]);
    }

    public function testUnlinkedAccountsNeverGainFinancialAccessFromMatchingEmail(): void
    {
        $token = $this->login('donor');
        $this->call('GET', '/api/giving/gifts?from=2026-01-01&to=2026-12-31', $token);
        self::assertSame(403, $this->code());
        $this->put($this->donorAccount, $this->donor, true, 0, $token);
        self::assertSame(403, $this->code(), 'an account cannot verify itself');
        self::assertSame(0, $this->rows('giving_donor_links'));
    }

    public function testOnlineFundsRequireExplicitOptInAndCurrentVersion(): void
    {
        $this->call('GET', '/api/giving/donor-access', $this->admin);
        self::assertFalse($this->json()['funds'][0]['enabled'], 'active funds are not offered online automatically');
        $this->offer(true, 0);
        self::assertSame(204, $this->code(), (string) $this->client->getResponse()->getContent());
        $this->offer(false, 0);
        self::assertSame([409, 'This record changed. Refresh before trying again.'], [$this->code(), $this->message()]);
        $this->offer(false, 1);
        self::assertSame(204, $this->code());

        $this->call('GET', '/api/giving/donor-access', $this->admin);
        self::assertSame([['id' => $this->fund, 'name' => 'General', 'description' => '', 'active' => true, 'enabled' => false, 'version' => 2]], $this->json()['funds']);
        self::assertSame(['Offered to donors online.', 'Removed from online giving.'], array_column($this->connection->fetchAllAssociative("SELECT reason FROM giving_history WHERE action = 'ONLINE_GIVING_CHANGED' ORDER BY occurred_at"), 'reason'));

        $this->call('PUT', "/api/giving/funds/{$this->fund}", $this->admin, ['expectedVersion' => 2, 'name' => 'General', 'active' => false, 'reason' => 'Closed']);
        self::assertSame(204, $this->code(), (string) $this->client->getResponse()->getContent());
        $this->offer(true, 3);
        self::assertSame([400, 'Activate this fund before offering it online.'], [$this->code(), $this->message()]);
    }

    public function testRevocationIsImmediateVersionedAndIdentityCannotBeReassigned(): void
    {
        $this->link(true, 0);
        $this->put($this->donorAccount, $this->person('Another'), true, 1);
        self::assertSame(409, $this->code());
        self::assertStringStartsWith('A verified account cannot be reassigned to another donor.', $this->message());
        $this->link(false, 1);
        self::assertSame(2, $this->rows('giving_donor_link_history'));
        $this->link(true, 2);

        $this->call('GET', '/api/giving/donor-access', $this->admin);
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame(['links', 'funds', 'hasMore'], array_keys($this->json()));
        self::assertSame([['accountId' => $this->donorAccount, 'accountName' => 'Donor', 'personId' => $this->donor, 'personName' => 'Donor Mabasa', 'active' => true, 'version' => 3]], $this->json()['links']);
        self::assertFalse($this->json()['hasMore']);
    }

    public function testLinkUniquenessAndBothAdministrativePermissionsAreRequired(): void
    {
        $this->link(true, 0);
        $other = $this->account('other');
        $this->put($other, $this->donor, true, 0);
        self::assertSame(409, $this->code(), 'one person, one account');
        $this->put($other, $this->person('Other'), false, 0);
        self::assertSame([409, 'Refresh donor access before trying again.'], [$this->code(), $this->message()]);
        $this->put($other, $this->person('Third'), true, 1);
        self::assertSame(409, $this->code());

        $this->accountWith('treasurer', ['giving.manage', 'giving.read']);
        $this->accountWith('registrar', ['identity.manage', 'people.manage']);
        foreach ([$this->login('treasurer'), $this->login('registrar')] as $token) {
            $this->call('GET', '/api/giving/donor-access', $token);
            self::assertSame(403, $this->code());
            $this->put($other, $this->donor, true, 0, $token);
            self::assertSame(403, $this->code());
        }
        $this->call('PUT', "/api/giving/online-funds/{$this->fund}", $this->login('registrar'), ['enabled' => true, 'expectedVersion' => 0]);
        self::assertSame(403, $this->code());
        $this->call('PUT', "/api/giving/online-funds/{$this->fund}", $this->login('treasurer'), ['enabled' => true, 'expectedVersion' => 0]);
        self::assertSame(204, $this->code(), 'giving.manage alone may offer a fund online');
        self::assertSame(1, $this->rows('giving_donor_links'));
    }

    public function testTheOnlineFundCatalogueNeedsOnlyGivingManageAndShowsEachFundsState(): void
    {
        $this->accountWith('treasurer', ['giving.manage']);
        $this->accountWith('auditor', ['giving.read']);
        $this->call('GET', '/api/giving/online-funds', $this->login('auditor'));
        self::assertSame(403, $this->code());
        $this->call('GET', '/api/giving/online-funds', $this->login('treasurer'));
        self::assertSame(200, $this->code(), (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        $entry = array_values(array_filter($this->json()['funds'], fn (array $f): bool => $f['id'] === $this->fund))[0];
        self::assertFalse($entry['enabled']);
        $this->offer(true, $entry['version']);
        $this->call('GET', '/api/giving/online-funds', $this->login('treasurer'));
        $entry = array_values(array_filter($this->json()['funds'], fn (array $f): bool => $f['id'] === $this->fund))[0];
        self::assertTrue($entry['enabled']);
        self::assertFalse($this->json()['truncated']);
    }

    public function testDonorIdentityMustAgreeWithMemberIdentityInBothDirections(): void
    {
        $member = $this->account('member');
        $this->put($member, $this->donor, true, 0);
        self::assertSame(204, $this->code(), (string) $this->client->getResponse()->getContent());
        $this->call('PUT', "/api/member-access/$member", $this->admin, self::body($this->person('Different'), true, 0, 'Wrong member identity'));
        self::assertSame(409, $this->code(), 'the donor link vetoes a contradicting member link');
        $this->put($member, $this->donor, false, 1);
        $this->call('PUT', "/api/member-access/$member", $this->admin, self::body($this->person('Still different'), true, 0, 'Wrong member identity'));
        self::assertSame(409, $this->code(), 'a revoked donor link still binds the person');

        $this->call('PUT', "/api/member-access/{$this->donorAccount}", $this->admin, self::body($this->person('Member'), true, 0, 'Checked ID'));
        self::assertSame(204, $this->code(), (string) $this->client->getResponse()->getContent());
        $this->put($this->donorAccount, $this->donor, true, 0);
        self::assertSame([409, 'The donor must match the verified member identity.'], [$this->code(), $this->message()]);
        self::assertSame(1, $this->rows('giving_donor_links'));
    }

    public function testNewLinksNeedAnActiveAccountAndAnAvailablePerson(): void
    {
        $disabled = $this->account('gone', status: 'disabled');
        $this->put($disabled, $this->donor, true, 0);
        self::assertSame([400, 'Choose an active account and available person.'], [$this->code(), $this->message()]);
        $this->put($this->donorAccount, Uuid::v4()->toRfc4122(), true, 0);
        self::assertSame([400, 'Choose an active account and available person.'], [$this->code(), $this->message()]);
        self::assertSame(0, $this->rows('giving_donor_links'));
    }

    public function testAFailedLinkRollsBackTheWholeCommand(): void
    {
        // The history row is the last write; making it fail proves the link upsert rolls back with it.
        $this->connection->executeStatement("CREATE FUNCTION test_reject_link_history() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'history unavailable'; END $$");
        $this->connection->executeStatement('CREATE TRIGGER test_reject_link_history BEFORE INSERT ON giving_donor_link_history FOR EACH ROW EXECUTE FUNCTION test_reject_link_history()');
        try {
            $this->put($this->donorAccount, $this->donor, true, 0);
            self::assertSame(500, $this->code());
        } finally {
            $this->connection->executeStatement('DROP TRIGGER test_reject_link_history ON giving_donor_link_history');
            $this->connection->executeStatement('DROP FUNCTION test_reject_link_history()');
        }
        self::assertSame(0, $this->rows('giving_donor_links'));
        $this->link(true, 0);
    }

    public function testUnknownFundsAreNotFound(): void
    {
        $this->call('PUT', '/api/giving/online-funds/00000000-0000-4000-8000-0000000000ff', $this->admin, ['enabled' => true, 'expectedVersion' => 0]);
        self::assertSame([404, 'The Giving record was not found.'], [$this->code(), $this->message()]);
    }

    public function testMalformedInputIsRejected(): void
    {
        $body = self::body($this->donor, true, 0);
        foreach ([['personId' => null], ['personId' => 'nope'], ['active' => null], ['active' => 'yes'], ['expectedVersion' => null], ['expectedVersion' => -1], ['evidence' => null], ['evidence' => ' '], ['evidence' => str_repeat('e', 1001)]] as $change) {
            $this->call('PUT', "/api/giving/donor-access/{$this->donorAccount}", $this->admin, array_filter(array_replace($body, $change), static fn (mixed $v): bool => null !== $v));
            self::assertSame(400, $this->code(), (string) json_encode($change));
        }
        foreach ([['enabled' => true], ['expectedVersion' => 0], ['enabled' => 'yes', 'expectedVersion' => 0], ['enabled' => true, 'expectedVersion' => -1]] as $fund) {
            $this->call('PUT', "/api/giving/online-funds/{$this->fund}", $this->admin, $fund);
            self::assertSame(400, $this->code(), (string) json_encode($fund));
        }
        foreach (['PUT /api/giving/donor-access/nope', 'PUT /api/giving/online-funds/nope'] as $route) {
            [$method, $uri] = explode(' ', $route);
            $this->call($method, $uri, $this->admin, $body + ['enabled' => true]);
            self::assertSame(400, $this->code(), $route);
        }
        foreach (['limit=0', 'limit=101', 'offset=-1', 'offset=100001', 'limit=x'] as $query) {
            $this->call('GET', "/api/giving/donor-access?$query", $this->admin);
            self::assertSame(400, $this->code(), $query);
        }
        self::assertSame(0, $this->rows('giving_donor_links'));
        self::assertSame(0, $this->rows('giving_online_funds'));
    }

    public function testAnonymousCallersAreRejected(): void
    {
        $this->call('GET', '/api/giving/donor-access');
        self::assertSame(401, $this->code());
        $this->call('PUT', "/api/giving/donor-access/{$this->donorAccount}", null, self::body($this->donor, true, 0));
        self::assertSame(401, $this->code());
        $this->call('PUT', "/api/giving/online-funds/{$this->fund}", null, ['enabled' => true, 'expectedVersion' => 0]);
        self::assertSame(401, $this->code());
    }
}
