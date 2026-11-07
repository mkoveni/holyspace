<?php

declare(strict_types=1);

namespace App\Tests\Giving\Presentation;

use App\Tests\Support\IdentityApiTestCase;
use Symfony\Component\Uid\Uuid;

/** Java's pledge cases from GivingReportingApiIntegrationTest, plus the shape, permission and malformed-input checks. */
final class GivingPledgesApiTest extends IdentityApiTestCase
{
    private string $manager;
    private string $reader;
    private ?string $directory = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountWith('treasurer', ['giving.manage', 'giving.read', 'giving.reverse']);
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
        if (null === $this->directory) {
            $this->accountWith('directory', ['people.manage']);
            $this->directory = $this->login('directory');
        }
        $this->call('POST', '/api/people', $this->directory, ['person' => ['firstName' => $firstName, 'lastName' => 'Mabasa']]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    /** @return array<string, mixed> */
    private function pledgeBody(string $donor, string $fund, int $amount = 10000): array
    {
        return ['requestId' => Uuid::v4()->toRfc4122(), 'donorId' => $donor, 'fundId' => $fund, 'amountMinor' => $amount, 'currency' => 'ZAR', 'startsOn' => '2026-01-01', 'endsOn' => '2026-12-31'];
    }

    private function pledge(string $donor, string $fund, int $amount = 10000): string
    {
        $this->call('POST', '/api/giving/pledges', $this->manager, $this->pledgeBody($donor, $fund, $amount));
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    private function gift(?string $donor, string $fund, int $amount = 10000, string $date = '2026-01-05'): string
    {
        $this->call('POST', '/api/giving/gifts', $this->manager, [
            'requestId' => Uuid::v4()->toRfc4122(), 'donorId' => $donor, 'amountMinor' => $amount, 'currency' => 'ZAR', 'receivedOn' => $date,
            'method' => 'BANK', 'allocations' => [['fundId' => $fund, 'amountMinor' => $amount]],
        ]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    /** @param array<string, mixed> $extra */
    private function assign(string $pledge, string $gift, int $amount, int $version, array $extra = []): void
    {
        $this->call('POST', "/api/giving/pledges/$pledge/assignments", $this->manager, $extra + ['requestId' => Uuid::v4()->toRfc4122(), 'giftId' => $gift, 'amountMinor' => $amount, 'expectedVersion' => $version]);
    }

    private function assigned(string $pledge, string $gift, int $amount, int $version): string
    {
        $this->assign($pledge, $gift, $amount, $version);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    /** @return array<string, mixed> */
    private function progress(string $pledge): array
    {
        $this->call('GET', "/api/giving/pledges/$pledge", $this->reader);
        self::assertSame(200, $this->code(), $this->message());

        return $this->json()['summary'];
    }

    private function reverse(string $gift): void
    {
        $this->call('POST', "/api/giving/gifts/$gift/reversals", $this->manager, ['requestId' => Uuid::v4()->toRfc4122(), 'reversedOn' => '2026-02-01', 'reason' => 'Duplicate ledger entry']);
        self::assertSame(201, $this->code(), $this->message());
    }

    public function testPledgesRequireExplicitAssignmentsAndReversalsReduceProgress(): void
    {
        $fund = $this->fund();
        $donor = $this->person('Donor');
        $pledge = $this->pledge($donor, $fund);
        $gift = $this->gift($donor, $fund);

        self::assertSame(0, $this->progress($pledge)['creditedMinor']);
        $this->assigned($pledge, $gift, 10000, 0);
        self::assertTrue($this->progress($pledge)['fulfilled']);
        $this->reverse($gift);
        $summary = $this->progress($pledge);
        self::assertSame([0, 10000, false], [$summary['creditedMinor'], $summary['remainingMinor'], $summary['fulfilled']]);
        $this->call('GET', "/api/giving/pledges/$pledge", $this->reader);
        self::assertTrue($this->json()['assignments'][0]['giftReversed']);
    }

    public function testAllocationsCannotBeDoubleSpentAndCancellationPreservesEvidence(): void
    {
        $fund = $this->fund();
        $donor = $this->person('Donor');
        $first = $this->pledge($donor, $fund);
        $second = $this->pledge($donor, $fund);
        $gift = $this->gift($donor, $fund);

        $link = $this->assigned($first, $gift, 6000, 0);
        $this->assign($second, $gift, 5000, 0);
        self::assertSame(409, $this->code());
        self::assertSame('The assignment exceeds the available gift allocation or remaining pledge target.', $this->message());
        $this->call('POST', "/api/giving/pledge-assignments/$link/cancel", $this->manager, ['expectedVersion' => 0, 'reason' => 'Assigned to wrong commitment']);
        self::assertSame(204, $this->code());
        $this->assigned($second, $gift, 10000, 0);
        self::assertSame(2, $this->rows('giving_pledge_assignments'));

        $this->call('POST', "/api/giving/pledges/$first/cancel", $this->manager, ['expectedVersion' => 2, 'reason' => 'Commitment withdrawn']);
        self::assertSame(204, $this->code());
        $this->assign($first, $gift, 100, 3);
        self::assertSame(409, $this->code());
        self::assertSame('This pledge is cancelled.', $this->message());

        $this->call('GET', "/api/giving/pledges/$first", $this->reader);
        $detail = $this->json();
        self::assertSame(['CANCELLED', 3, 'Commitment withdrawn'], [$detail['summary']['pledge']['status'], $detail['summary']['pledge']['version'], $detail['summary']['pledge']['cancellationReason']]);
        self::assertSame('CANCELLED', $detail['assignments'][0]['assignment']['status']);
        self::assertSame('Assigned to wrong commitment', $detail['assignments'][0]['assignment']['cancellationReason']);
        self::assertSame(['PLEDGE_CANCELLED', 'ASSIGNMENT_CANCELLED', 'GIFT_ASSIGNED', 'PLEDGE_CREATED'], array_column($detail['history'], 'action'));
        $this->call('GET', "/api/giving/pledges/$first/candidates", $this->reader);
        self::assertSame([], $this->json(), 'a cancelled pledge has no candidates');
    }

    public function testAssignmentsCheckDonorFundCurrencyDatesVersionsAndExactCents(): void
    {
        $fund = $this->fund();
        $donor = $this->person('Donor');
        $pledge = $this->pledge($donor, $fund);
        $other = $this->gift($this->person('Other'), $fund);

        $this->assign($pledge, $other, 100, 0);
        self::assertSame(400, $this->code());
        self::assertSame('Choose a gift from this donor, currency and commitment period.', $this->message());
        $wrongFund = $this->gift($donor, $this->fund('Missions'));
        $this->assign($pledge, $wrongFund, 100, 0);
        self::assertSame(409, $this->code());
        $gift = $this->gift($donor, $fund);
        $this->assign($pledge, $gift, 100, 1);
        self::assertSame(409, $this->code());
        self::assertSame('This record changed. Refresh before trying again.', $this->message());
        $this->call('POST', "/api/giving/pledges/$pledge/assignments", $this->manager, ['requestId' => Uuid::v4()->toRfc4122(), 'giftId' => $gift, 'expectedVersion' => 0, 'amountMinor' => 1.9]);
        self::assertSame(400, $this->code());

        $this->call('GET', "/api/giving/pledges/$pledge/candidates", $this->reader);
        self::assertSame(200, $this->code());
        self::assertSame([['giftId' => $gift, 'receivedOn' => '2026-01-05', 'availableMinor' => 10000, 'reference' => null, 'method' => 'BANK']], $this->json());
        self::assertSame(0, $this->rows('giving_pledge_assignments'));
    }

    public function testAssignmentRetriesStayIdempotentAfterThePledgeVersionChanges(): void
    {
        $fund = $this->fund();
        $donor = $this->person('Donor');
        $pledge = $this->pledge($donor, $fund);
        $gift = $this->gift($donor, $fund);
        $body = ['requestId' => strtoupper(Uuid::v4()->toRfc4122()), 'giftId' => $gift, 'amountMinor' => 10000, 'expectedVersion' => 0];

        $this->call('POST', "/api/giving/pledges/$pledge/assignments", $this->manager, $body);
        self::assertSame(201, $this->code(), $this->message());
        $assigned = (string) $this->json()['id'];
        self::assertSame(strtolower((string) $body['requestId']), $assigned);
        $this->call('POST', "/api/giving/pledges/$pledge/assignments", $this->manager, $body);
        self::assertSame(201, $this->code());
        self::assertSame($assigned, $this->json()['id']);
        $this->call('POST', "/api/giving/pledges/$pledge/assignments", $this->manager, ['amountMinor' => 5000] + $body);
        self::assertSame(409, $this->code());
        self::assertSame('This request identifier was used for different details.', $this->message());
        self::assertSame(1, $this->rows('giving_pledge_assignments'));
        self::assertSame(1, $this->rows('giving_history', "action = 'GIFT_ASSIGNED'"));
    }

    public function testCreationIsIdempotentAndChecksDonorAndFund(): void
    {
        $fund = $this->fund();
        $donor = $this->person('Donor');
        $body = $this->pledgeBody($donor, $fund);
        $this->call('POST', '/api/giving/pledges', $this->manager, $body);
        self::assertSame(201, $this->code(), $this->message());
        $id = (string) $this->json()['id'];
        self::assertSame($body['requestId'], $id);
        $this->call('POST', '/api/giving/pledges', $this->manager, $body);
        self::assertSame([201, $id], [$this->code(), $this->json()['id']]);
        $this->call('POST', '/api/giving/pledges', $this->manager, ['amountMinor' => 5] + $body);
        self::assertSame(409, $this->code());

        foreach ([
            [['donorId' => Uuid::v4()->toRfc4122()], 400, 'Choose an available donor.'],
            [['fundId' => Uuid::v4()->toRfc4122()], 409, 'Choose an active fund.'],
            [['endsOn' => '2036-01-10'], 400, 'A pledge needs a donor, fund, currency and a commitment period of at most ten years.'],
            [['endsOn' => '2025-12-31'], 400, 'A pledge needs a donor, fund, currency and a commitment period of at most ten years.'],
            [['currency' => 'JPY'], 400, 'Choose ZAR, USD, EUR or GBP. All supported currencies use two decimal places.'],
        ] as [$change, $status, $message]) {
            $this->call('POST', '/api/giving/pledges', $this->manager, ['requestId' => Uuid::v4()->toRfc4122()] + $change + $body);
            self::assertSame($status, $this->code(), $message);
            self::assertSame($message, $this->message());
        }
        self::assertSame(1, $this->rows('giving_pledges'));
        self::assertSame(1, $this->rows('giving_history', "action = 'PLEDGE_CREATED'"));
    }

    public function testListAndDetailMatchTheJavaShape(): void
    {
        $fund = $this->fund();
        $donor = $this->person('Donor');
        $late = $this->pledge($donor, $fund);
        $this->call('POST', '/api/giving/pledges', $this->manager, ['endsOn' => '2026-06-30'] + $this->pledgeBody($donor, $fund, 5000));
        $early = (string) $this->json()['id'];
        $this->pledge($this->person('Other'), $fund);
        $gift = $this->gift($donor, $fund);
        $assignment = $this->assigned($late, $gift, 4000, 0);

        $this->call('GET', "/api/giving/pledges?donorId=$donor&limit=1", $this->reader);
        self::assertSame(200, $this->code());
        $page = $this->json();
        self::assertSame(2, $page['total']);
        self::assertSame($early, $page['items'][0]['pledge']['id'], 'ordered by end date');
        self::assertSame(['pledge', 'donorName', 'fundName', 'creditedMinor', 'remainingMinor', 'fulfilled'], array_keys($page['items'][0]));
        self::assertSame(
            ['id', 'donorId', 'fundId', 'target', 'startsOn', 'endsOn', 'status', 'version', 'cancellationReason', 'createdBy', 'createdAt'],
            array_keys($page['items'][0]['pledge']),
        );
        self::assertSame(['amountMinor' => 5000, 'currency' => 'ZAR'], $page['items'][0]['pledge']['target']);
        self::assertSame(['Donor Mabasa', 'General'], [$page['items'][0]['donorName'], $page['items'][0]['fundName']]);
        $this->call('GET', '/api/giving/pledges?status=CANCELLED', $this->reader);
        self::assertSame(0, $this->json()['total']);
        $this->call('GET', '/api/giving/pledges', $this->reader);
        self::assertSame(3, $this->json()['total']);

        $this->call('GET', "/api/giving/pledges/$late", $this->reader);
        $detail = $this->json();
        self::assertSame(['summary', 'assignments', 'history'], array_keys($detail));
        self::assertSame([4000, 6000, false, 1], [$detail['summary']['creditedMinor'], $detail['summary']['remainingMinor'], $detail['summary']['fulfilled'], $detail['summary']['pledge']['version']]);
        self::assertSame(['assignment', 'receivedOn', 'giftReversed'], array_keys($detail['assignments'][0]));
        self::assertSame(
            ['id', 'pledgeId', 'giftId', 'fundId', 'donorId', 'currency', 'amountMinor', 'status', 'version', 'cancellationReason', 'cancelledBy', 'cancelledAt', 'createdBy', 'createdAt'],
            array_keys($detail['assignments'][0]['assignment']),
        );
        self::assertSame([$assignment, $late, $gift, 'ACTIVE', null], [
            $detail['assignments'][0]['assignment']['id'], $detail['assignments'][0]['assignment']['pledgeId'], $detail['assignments'][0]['assignment']['giftId'],
            $detail['assignments'][0]['assignment']['status'], $detail['assignments'][0]['assignment']['cancelledAt'],
        ]);
        self::assertSame(['2026-01-05', false], [$detail['assignments'][0]['receivedOn'], $detail['assignments'][0]['giftReversed']]);
        self::assertSame(['action', 'reason', 'actorId', 'occurredAt'], array_keys($detail['history'][0]));

        $this->call('GET', "/api/giving/pledges/$late/candidates", $this->reader);
        self::assertSame(6000, $this->json()[0]['availableMinor'], 'the assigned part is no longer available');
        $this->call('GET', "/api/giving/pledges/$late?offset=1", $this->reader);
        self::assertSame([], $this->json()['assignments']);
    }

    public function testAFailedAssignmentRollsBackTheWholeCommand(): void
    {
        $fund = $this->fund();
        $donor = $this->person('Donor');
        $pledge = $this->pledge($donor, $fund);
        $gift = $this->gift($donor, $fund);
        // The audit row is the last write; making it fail proves the assignment insert and the pledge version roll back with it.
        $this->connection->executeStatement("CREATE FUNCTION test_reject_assignment_audit() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'audit unavailable'; END $$");
        $this->connection->executeStatement("CREATE TRIGGER test_reject_assignment_audit BEFORE INSERT ON giving_history FOR EACH ROW WHEN (NEW.action = 'GIFT_ASSIGNED') EXECUTE FUNCTION test_reject_assignment_audit()");
        try {
            $this->assign($pledge, $gift, 1000, 0);
            self::assertSame(500, $this->code());
        } finally {
            $this->connection->executeStatement('DROP TRIGGER test_reject_assignment_audit ON giving_history');
            $this->connection->executeStatement('DROP FUNCTION test_reject_assignment_audit()');
        }
        self::assertSame(0, $this->rows('giving_pledge_assignments'));
        self::assertSame(0, $this->progress($pledge)['pledge']['version']);
        $this->assigned($pledge, $gift, 1000, 0);
    }

    public function testUnknownRecordsAreNotFound(): void
    {
        $unknown = '00000000-0000-4000-8000-0000000000aa';
        foreach (['GET /api/giving/pledges/'.$unknown, 'GET /api/giving/pledges/'.$unknown.'/candidates'] as $route) {
            [$method, $uri] = explode(' ', $route);
            $this->call($method, $uri, $this->reader);
            self::assertSame(404, $this->code(), $route);
            self::assertSame('The Giving record was not found.', $this->message());
        }
        $fund = $this->fund();
        $donor = $this->person('Donor');
        $pledge = $this->pledge($donor, $fund);
        foreach ([
            ["/api/giving/pledges/$unknown/cancel", ['expectedVersion' => 0, 'reason' => 'Gone']],
            ["/api/giving/pledge-assignments/$unknown/cancel", ['expectedVersion' => 0, 'reason' => 'Gone']],
            ["/api/giving/pledges/$unknown/assignments", ['requestId' => Uuid::v4()->toRfc4122(), 'giftId' => $this->gift($donor, $fund), 'expectedVersion' => 0, 'amountMinor' => 100]],
            ["/api/giving/pledges/$pledge/assignments", ['requestId' => Uuid::v4()->toRfc4122(), 'giftId' => $unknown, 'expectedVersion' => 0, 'amountMinor' => 100]],
        ] as [$uri, $body]) {
            $this->call('POST', $uri, $this->manager, $body);
            self::assertSame(404, $this->code(), $uri);
        }
    }

    public function testMalformedInputIsRejected(): void
    {
        $fund = $this->fund();
        $donor = $this->person('Donor');
        $body = $this->pledgeBody($donor, $fund);
        foreach ([
            ['requestId' => null], ['requestId' => 'nope'], ['donorId' => null], ['donorId' => 'nope'], ['fundId' => 'nope'],
            ['amountMinor' => 100.9], ['amountMinor' => '100'], ['amountMinor' => 0], ['amountMinor' => 1_000_000_000_001], ['amountMinor' => null],
            ['currency' => ''], ['startsOn' => null], ['endsOn' => '31/12/2026'],
        ] as $change) {
            $this->call('POST', '/api/giving/pledges', $this->manager, array_replace($body, $change));
            self::assertSame(400, $this->code(), (string) json_encode($change));
        }
        self::assertSame(0, $this->rows('giving_pledges'));

        $pledge = $this->pledge($donor, $fund);
        $gift = $this->gift($donor, $fund);
        $assign = ['requestId' => Uuid::v4()->toRfc4122(), 'giftId' => $gift, 'expectedVersion' => 0, 'amountMinor' => 100];
        foreach ([['requestId' => 'nope'], ['giftId' => null], ['expectedVersion' => -1], ['expectedVersion' => null], ['amountMinor' => 0], ['amountMinor' => '1']] as $change) {
            $this->call('POST', "/api/giving/pledges/$pledge/assignments", $this->manager, array_filter(array_replace($assign, $change), static fn (mixed $v): bool => null !== $v));
            self::assertSame(400, $this->code(), (string) json_encode($change));
        }
        foreach ([['reason' => ''], ['reason' => str_repeat('r', 1001)], ['expectedVersion' => -1], ['reason' => 'Gone', 'expectedVersion' => null]] as $change) {
            $this->call('POST', "/api/giving/pledges/$pledge/cancel", $this->manager, array_filter(array_replace(['expectedVersion' => 0, 'reason' => 'Gone'], $change), static fn (mixed $v): bool => null !== $v));
            self::assertSame(400, $this->code(), (string) json_encode($change));
        }
        foreach (['GET /api/giving/pledges/nope', 'GET /api/giving/pledges/nope/candidates', 'POST /api/giving/pledges/nope/cancel', 'POST /api/giving/pledge-assignments/nope/cancel'] as $route) {
            [$method, $uri] = explode(' ', $route);
            $this->call($method, $uri, $this->manager, 'POST' === $method ? ['expectedVersion' => 0, 'reason' => 'Gone'] : null);
            self::assertSame(400, $this->code(), $route);
        }
        foreach (['status=open', 'donorId=nope', 'limit=0', 'limit=101', 'offset=-1', 'offset=100001', 'limit=x'] as $query) {
            $this->call('GET', "/api/giving/pledges?$query", $this->reader);
            self::assertSame(400, $this->code(), $query);
        }
        $this->call('GET', '/api/giving/pledges?status=open', $this->reader);
        self::assertSame('Choose open or cancelled pledges.', $this->message());
        foreach (["/api/giving/pledges/$pledge?limit=101", "/api/giving/pledges/$pledge/candidates?offset=-1"] as $uri) {
            $this->call('GET', $uri, $this->reader);
            self::assertSame(400, $this->code(), $uri);
        }
        self::assertSame(0, $this->rows('giving_pledge_assignments'));
        self::assertSame(1, $this->rows('giving_history', "target_type = 'PLEDGE'"));
    }

    public function testPledgesNeedFinancialPermissions(): void
    {
        $fund = $this->fund();
        $donor = $this->person('Donor');
        $pledge = $this->pledge($donor, $fund);

        $this->call('GET', '/api/giving/pledges');
        self::assertSame(401, $this->code());
        $this->call('POST', '/api/giving/pledges', null, $this->pledgeBody($donor, $fund));
        self::assertSame(401, $this->code());

        foreach ([
            ['/api/giving/pledges', $this->pledgeBody($donor, $fund)],
            ["/api/giving/pledges/$pledge/cancel", ['expectedVersion' => 0, 'reason' => 'Gone']],
            ["/api/giving/pledges/$pledge/assignments", ['requestId' => Uuid::v4()->toRfc4122(), 'giftId' => $pledge, 'expectedVersion' => 0, 'amountMinor' => 100]],
            ['/api/giving/pledge-assignments/00000000-0000-4000-8000-0000000000aa/cancel', ['expectedVersion' => 0, 'reason' => 'Gone']],
        ] as [$uri, $body]) {
            $this->call('POST', $uri, $this->reader, $body);
            self::assertSame(403, $this->code(), $uri);
        }

        $this->accountWith('bookkeeper', ['giving.manage']);
        $this->call('GET', "/api/giving/pledges/$pledge", $this->login('bookkeeper'));
        self::assertSame(200, $this->code(), 'managing implies reading');

        $this->accountWith('pastor', ['people.manage', 'people.read']);
        $pastor = $this->login('pastor');
        foreach (['/api/giving/pledges', "/api/giving/pledges/$pledge", "/api/giving/pledges/$pledge/candidates"] as $uri) {
            $this->call('GET', $uri, $pastor);
            self::assertSame(403, $this->code(), 'People permissions grant no Giving access: '.$uri);
        }
        self::assertSame(1, $this->rows('giving_pledges'));
    }
}
