<?php

declare(strict_types=1);

namespace App\Tests\Giving\Presentation;

use App\Tests\Support\IdentityApiTestCase;
use Symfony\Component\Uid\Uuid;

/** Java's acknowledgement cases from GivingReportingApiIntegrationTest, plus the shape, permission and malformed-input checks. */
final class GivingAcknowledgementsApiTest extends IdentityApiTestCase
{
    private string $manager;
    private string $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountWith('treasurer', ['giving.manage', 'giving.read', 'giving.reverse', 'giving.acknowledge']);
        $this->accountWith('auditor', ['giving.read']);
        $this->manager = $this->login('treasurer');
        $this->reader = $this->login('auditor');
    }

    private function fund(string $name = 'General'): string
    {
        $this->call('POST', '/api/giving/funds', $this->manager, ['requestId' => Uuid::v4()->toRfc4122(), 'name' => $name]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    private function person(string $firstName): string
    {
        $this->accountWith('directory', ['people.manage']);
        $this->call('POST', '/api/people', $this->login('directory'), ['person' => ['firstName' => $firstName, 'lastName' => 'Mabasa']]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    /** @param list<array{fundId: string, amountMinor: int}> $allocations */
    private function gift(array $allocations, ?string $donor = null, ?string $reference = null): string
    {
        $this->call('POST', '/api/giving/gifts', $this->manager, [
            'requestId' => Uuid::v4()->toRfc4122(), 'donorId' => $donor, 'amountMinor' => array_sum(array_column($allocations, 'amountMinor')), 'currency' => 'ZAR',
            'receivedOn' => '2026-01-05', 'method' => 'BANK', 'externalReference' => $reference, 'allocations' => $allocations,
        ]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    /** @param array<string, mixed> $body */
    private function issue(string $gift, array $body = [], ?string $token = null): void
    {
        $this->call('POST', "/api/giving/gifts/$gift/acknowledgements", $token ?? $this->manager, $body + ['requestId' => Uuid::v4()->toRfc4122()]);
    }

    private function issued(string $gift): string
    {
        $this->issue($gift);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    /** @return array<int|string, mixed> */
    private function acknowledgement(string $id): array
    {
        $this->call('GET', "/api/giving/acknowledgements/$id", $this->reader);
        self::assertSame(200, $this->code(), $this->message());

        return $this->json();
    }

    public function testAcknowledgementNumbersAreUniqueSnapshotsAndBecomeVoidOnReversal(): void
    {
        $fund = $this->fund();
        $gift = $this->gift([['fundId' => $fund, 'amountMinor' => 10000]], $this->person('Donor'));
        $body = ['requestId' => strtoupper(Uuid::v4()->toRfc4122())];

        $this->issue($gift, $body);
        self::assertSame(201, $this->code(), $this->message());
        $id = (string) $this->json()['id'];
        self::assertSame(strtolower((string) $body['requestId']), $id);
        $this->issue($gift, $body);
        self::assertSame([201, $id], [$this->code(), $this->json()['id']], 'an identical retry returns the same acknowledgement');
        $number = (string) $this->acknowledgement($id)['number'];
        self::assertStringStartsWith('G-', $number);

        $this->call('PUT', "/api/giving/funds/$fund", $this->manager, ['expectedVersion' => 0, 'name' => 'Renamed', 'description' => '', 'active' => true, 'reason' => 'Updated display name']);
        self::assertSame(204, $this->code());
        self::assertSame('General', $this->acknowledgement($id)['lines'][0]['fundName'], 'fund names are frozen at issue');

        $this->call('POST', "/api/giving/gifts/$gift/reversals", $this->manager, ['requestId' => Uuid::v4()->toRfc4122(), 'reversedOn' => '2026-02-01', 'reason' => 'Duplicate ledger entry']);
        self::assertSame(201, $this->code(), $this->message());
        $voided = $this->acknowledgement($id);
        self::assertSame([true, $number, '2026-02-01', 'Duplicate ledger entry'], [$voided['voided'], $voided['number'], $voided['reversedOn'], $voided['reversalReason']]);
        $this->issue($gift);
        self::assertSame(409, $this->code());
        self::assertSame('An acknowledgement cannot be issued for a reversed gift.', $this->message());
        $this->issue($gift, $body);
        self::assertSame([201, $id], [$this->code(), $this->json()['id']], 'a retry still succeeds after the reversal, as in Java');
        self::assertSame(1, $this->rows('giving_acknowledgements'));
        self::assertSame(1, $this->rows('giving_history', "action = 'ACKNOWLEDGEMENT_ISSUED'"));
    }

    public function testFinancialReadsNeverIssueAcknowledgements(): void
    {
        $gift = $this->gift([['fundId' => $this->fund(), 'amountMinor' => 10000]]);

        $this->call('GET', "/api/giving/gifts/$gift/acknowledgement", $this->reader);
        self::assertSame(200, $this->code());
        self::assertSame(['acknowledgement' => null], $this->json());
        $this->call('GET', '/api/giving/gifts/00000000-0000-4000-8000-0000000000ff/acknowledgement', $this->reader);
        self::assertSame([200, ['acknowledgement' => null]], [$this->code(), $this->json()], 'an unknown gift has no acknowledgement, as in Java');
        self::assertSame(0, $this->rows('giving_acknowledgements'));
    }

    public function testTheViewMatchesTheJavaShape(): void
    {
        $general = $this->fund('General');
        $alms = $this->fund('Alms');
        $gift = $this->gift([['fundId' => $general, 'amountMinor' => 7000], ['fundId' => $alms, 'amountMinor' => 3000]], reference: 'BANK-7');
        $id = $this->issued($gift);

        $this->call('GET', "/api/giving/gifts/$gift/acknowledgement", $this->reader);
        self::assertSame(['acknowledgement'], array_keys($this->json()));
        $view = $this->json()['acknowledgement'];
        self::assertSame($view, $this->acknowledgement($id));
        self::assertSame(
            ['id', 'giftId', 'number', 'donorName', 'issuedOn', 'receivedOn', 'amountMinor', 'currency', 'method', 'reference', 'voided', 'reversedOn', 'reversalReason', 'lines'],
            array_keys($view),
        );
        self::assertMatchesRegularExpression('/^G-\d{8}$/', $view['number']);
        self::assertSame([$id, $gift, 'Anonymous', '2026-01-05', 10000, 'ZAR', 'BANK', 'BANK-7', false, null, null], [
            $view['id'], $view['giftId'], $view['donorName'], $view['receivedOn'], $view['amountMinor'], $view['currency'], $view['method'], $view['reference'],
            $view['voided'], $view['reversedOn'], $view['reversalReason'],
        ]);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $view['issuedOn']);
        self::assertSame([['fundId' => $alms, 'fundName' => 'Alms', 'amountMinor' => 3000], ['fundId' => $general, 'fundName' => 'General', 'amountMinor' => 7000]], $view['lines']);

        $this->call('GET', "/api/giving/gifts/$gift", $this->reader);
        self::assertContains('ACKNOWLEDGEMENT_ISSUED', array_column($this->json()['history'], 'action'));
    }

    public function testOneAcknowledgementPerGiftAndRequestIdsAreNotReused(): void
    {
        $fund = $this->fund();
        $gift = $this->gift([['fundId' => $fund, 'amountMinor' => 10000]]);
        $body = ['requestId' => Uuid::v4()->toRfc4122()];
        $this->issue($gift, $body);
        self::assertSame(201, $this->code());

        $this->issue($gift);
        self::assertSame(409, $this->code());
        self::assertSame('This gift already has an acknowledgement. Refresh to open it.', $this->message());
        $this->issue($this->gift([['fundId' => $fund, 'amountMinor' => 500]]), $body);
        self::assertSame(409, $this->code());
        self::assertSame('This request identifier was already used.', $this->message());
        self::assertSame(1, $this->rows('giving_acknowledgements'));
    }

    public function testAFailedIssueRollsBackTheWholeCommand(): void
    {
        $gift = $this->gift([['fundId' => $this->fund(), 'amountMinor' => 10000]]);
        // The audit row is the last write; making it fail proves the acknowledgement and its lines roll back with it.
        $this->connection->executeStatement("CREATE FUNCTION test_reject_ack_audit() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'audit unavailable'; END $$");
        $this->connection->executeStatement("CREATE TRIGGER test_reject_ack_audit BEFORE INSERT ON giving_history FOR EACH ROW WHEN (NEW.action = 'ACKNOWLEDGEMENT_ISSUED') EXECUTE FUNCTION test_reject_ack_audit()");
        try {
            $this->issue($gift);
            self::assertSame(500, $this->code());
        } finally {
            $this->connection->executeStatement('DROP TRIGGER test_reject_ack_audit ON giving_history');
            $this->connection->executeStatement('DROP FUNCTION test_reject_ack_audit()');
        }
        self::assertSame(0, $this->rows('giving_acknowledgements'));
        self::assertSame(0, $this->rows('giving_acknowledgement_lines'));
        $this->issued($gift);
    }

    public function testUnknownRecordsAreNotFound(): void
    {
        $unknown = '00000000-0000-4000-8000-0000000000ff';
        $this->call('GET', "/api/giving/acknowledgements/$unknown", $this->reader);
        self::assertSame([404, 'The Giving record was not found.'], [$this->code(), $this->message()]);
        $this->issue($unknown);
        self::assertSame([404, 'The Giving record was not found.'], [$this->code(), $this->message()]);
    }

    public function testMalformedInputIsRejected(): void
    {
        $gift = $this->gift([['fundId' => $this->fund(), 'amountMinor' => 10000]]);
        foreach ([[], ['requestId' => null], ['requestId' => 'nope'], ['requestId' => 5]] as $body) {
            $this->call('POST', "/api/giving/gifts/$gift/acknowledgements", $this->manager, $body);
            self::assertSame(400, $this->code(), (string) json_encode($body));
        }
        foreach (['POST /api/giving/gifts/nope/acknowledgements', 'GET /api/giving/gifts/nope/acknowledgement', 'GET /api/giving/acknowledgements/nope'] as $route) {
            [$method, $uri] = explode(' ', $route);
            $this->call($method, $uri, $this->manager, 'POST' === $method ? ['requestId' => Uuid::v4()->toRfc4122()] : null);
            self::assertSame(400, $this->code(), $route);
        }
        self::assertSame(0, $this->rows('giving_acknowledgements'));
    }

    public function testIssuanceNeedsItsOwnPermission(): void
    {
        $gift = $this->gift([['fundId' => $this->fund(), 'amountMinor' => 10000]]);

        $this->call('POST', "/api/giving/gifts/$gift/acknowledgements", null, ['requestId' => Uuid::v4()->toRfc4122()]);
        self::assertSame(401, $this->code());
        $this->call('GET', "/api/giving/gifts/$gift/acknowledgement");
        self::assertSame(401, $this->code());
        $this->call('GET', '/api/giving/acknowledgements/00000000-0000-4000-8000-0000000000ff');
        self::assertSame(401, $this->code());

        $this->accountWith('bookkeeper', ['giving.manage', 'giving.read', 'giving.reverse']);
        $bookkeeper = $this->login('bookkeeper');
        $this->issue($gift, token: $bookkeeper);
        self::assertSame(403, $this->code(), 'managing gifts does not grant issuing acknowledgements');
        $this->issue($gift, token: $this->reader);
        self::assertSame(403, $this->code());

        $this->accountWith('clerk', ['giving.acknowledge']);
        $this->issue($gift, token: $this->login('clerk'));
        self::assertSame(201, $this->code(), 'giving.acknowledge alone is enough to issue, as in Java');
        $id = (string) $this->json()['id'];

        $this->call('GET', "/api/giving/acknowledgements/$id", $bookkeeper);
        self::assertSame(200, $this->code());
        $this->accountWith('pastor', ['people.manage', 'people.read']);
        $pastor = $this->login('pastor');
        foreach (["/api/giving/gifts/$gift/acknowledgement", "/api/giving/acknowledgements/$id"] as $uri) {
            $this->call('GET', $uri, $pastor);
            self::assertSame(403, $this->code(), 'People permissions grant no Giving access: '.$uri);
        }
        self::assertSame(1, $this->rows('giving_acknowledgements'));
    }
}
