<?php

declare(strict_types=1);

namespace App\Tests\Giving\Presentation;

use App\Tests\Support\IdentityApiTestCase;
use Symfony\Component\Uid\Uuid;

/** Java's reconciliation cases from GivingReportingApiIntegrationTest, plus the shape, permission and malformed-input checks. */
final class GivingReconciliationsApiTest extends IdentityApiTestCase
{
    private string $manager;
    private string $reader;
    private ?string $fund = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountWith('treasurer', ['giving.manage', 'giving.read', 'giving.reverse', 'giving.reconcile']);
        $this->accountWith('auditor', ['giving.read']);
        $this->manager = $this->login('treasurer');
        $this->reader = $this->login('auditor');
    }

    private function gift(int $amount = 10000, string $date = '2026-01-05', string $currency = 'ZAR'): string
    {
        if (null === $this->fund) {
            $this->call('POST', '/api/giving/funds', $this->manager, ['requestId' => Uuid::v4()->toRfc4122(), 'name' => 'General']);
            self::assertSame(201, $this->code(), $this->message());
            $this->fund = (string) $this->json()['id'];
        }
        $this->call('POST', '/api/giving/gifts', $this->manager, [
            'requestId' => Uuid::v4()->toRfc4122(), 'amountMinor' => $amount, 'currency' => $currency, 'receivedOn' => $date,
            'method' => 'BANK', 'allocations' => [['fundId' => $this->fund, 'amountMinor' => $amount]],
        ]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    private function reverse(string $gift): void
    {
        $this->call('POST', "/api/giving/gifts/$gift/reversals", $this->manager, ['requestId' => Uuid::v4()->toRfc4122(), 'reversedOn' => '2026-02-01', 'reason' => 'Duplicate ledger entry']);
        self::assertSame(201, $this->code(), $this->message());
    }

    /**
     * @param list<string> $gifts
     *
     * @return array<string, mixed>
     */
    private static function batch(array $gifts, int $expected, string $reference): array
    {
        return ['requestId' => Uuid::v4()->toRfc4122(), 'giftIds' => $gifts, 'expectedMinor' => $expected, 'currency' => 'ZAR', 'reconciledOn' => '2026-02-01', 'reference' => $reference, 'reason' => 'Verified bank evidence'];
    }

    /** @param array<string, mixed> $body */
    private function created(array $body): string
    {
        $this->call('POST', '/api/giving/reconciliations', $this->manager, $body);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    /** @param array<string, mixed> $body */
    private function reviewed(string $batch, array $body): string
    {
        $this->call('POST', "/api/giving/reconciliations/$batch/reviews", $this->manager, $body);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    /** @return array<int|string, mixed> */
    private function summary(string $batch): array
    {
        $this->call('GET', "/api/giving/reconciliations/$batch", $this->reader);
        self::assertSame(200, $this->code(), $this->message());

        return $this->json()['summary'];
    }

    /** @return array<string, mixed> */
    private static function review(int $version, string $note = 'Bank correction verified'): array
    {
        return ['requestId' => Uuid::v4()->toRfc4122(), 'expectedVersion' => $version, 'outcome' => 'EXPLAINED', 'note' => $note];
    }

    public function testReconciliationPreservesSnapshotsAndLaterReversalsReopenReview(): void
    {
        $gift = $this->gift();
        $body = self::batch([$gift], 10000, 'CASH-001');
        $batch = $this->created($body);
        self::assertSame($batch, $this->created($body), 'an identical retry returns the same batch');
        self::assertFalse($this->summary($batch)['needsReview']);

        $this->reverse($gift);
        self::assertTrue($this->summary($batch)['needsReview']);
        $review = self::review(0);
        $reviewed = $this->reviewed($batch, $review);
        self::assertSame($reviewed, $this->reviewed($batch, $review));
        self::assertFalse($this->summary($batch)['needsReview']);
        self::assertSame(1, $this->rows('giving_reconciliation_reviews'));
        self::assertSame(1, $this->rows('giving_history', "target_type = 'BATCH' AND action = 'BATCH_REVIEWED'"));
    }

    public function testMismatchesRequireEvidenceAndGiftBatchMembershipIsUnique(): void
    {
        $gift = $this->gift();
        $body = ['reason' => ''] + self::batch([$gift], 11000, 'BANK-001');
        $this->call('POST', '/api/giving/reconciliations', $this->manager, $body);
        self::assertSame(400, $this->code());
        self::assertSame('Discrepancy explanation is required and must be at most 1000 characters.', $this->message());
        $batch = $this->created(['reason' => 'Recorded bank charge pending review'] + $body);
        self::assertTrue($this->summary($batch)['needsReview']);

        $this->call('POST', '/api/giving/reconciliations', $this->manager, self::batch([$gift], 10000, 'BANK-002'));
        self::assertSame(409, $this->code());
        self::assertSame('Every selected gift must be unreconciled, unreversed, in the batch currency and received by the reconciliation date.', $this->message());
        self::assertSame(1, $this->rows('giving_reconciliation_items'));
    }

    public function testLaterSourceReversalsInvalidateExplainedReconciliationEvidence(): void
    {
        $first = $this->gift(10000, '2026-01-05');
        $second = $this->gift(10000, '2026-01-06');
        $batch = $this->created(self::batch([$first, $second], 20000, 'BANK-REV'));
        $this->reviewed($batch, self::review(0, 'Original totals verified'));

        $this->reverse($second);
        self::assertTrue($this->summary($batch)['needsReview']);
        $this->call('POST', "/api/giving/reconciliations/$batch/reviews", $this->manager, self::review(0, 'Stale review screen'));
        self::assertSame(409, $this->code());
        self::assertSame('This record changed. Refresh before trying again.', $this->message());
    }

    public function testFinancialReadsNeverCreateReconciliationEvidence(): void
    {
        $this->gift();
        $this->call('GET', '/api/giving/reconciliations', $this->reader);
        self::assertSame([200, ['items' => [], 'total' => 0]], [$this->code(), $this->json()]);
        self::assertSame(0, $this->rows('giving_reconciliation_items'));
    }

    public function testListAndDetailMatchTheJavaShape(): void
    {
        $first = $this->gift(10000, '2026-01-06');
        $second = $this->gift(2500, '2026-01-05');
        $older = $this->created(['reconciledOn' => '2026-01-20'] + self::batch([$this->gift(700, '2026-01-04')], 700, 'CASH-OLD'));
        $batch = $this->created(self::batch([$first, $second], 13000, ' BANK-001 '));
        $this->reverse($second);
        $this->reviewed($batch, ['outcome' => 'FOLLOW_UP'] + self::review(0, 'Waiting for the bank'));

        $this->call('GET', '/api/giving/reconciliations?limit=1', $this->reader);
        $page = $this->json();
        self::assertSame(2, $page['total']);
        self::assertSame($batch, $page['items'][0]['batch']['id'], 'newest reconciliation date first');
        self::assertSame(['batch', 'reversedMinor', 'currentNetMinor', 'version', 'review', 'needsReview'], array_keys($page['items'][0]));
        self::assertSame(['id', 'reference', 'expectedMinor', 'actualMinor', 'currency', 'reconciledOn', 'reason', 'createdBy', 'createdAt', 'giftIds'], array_keys($page['items'][0]['batch']));
        self::assertSame(['BANK-001', 13000, 12500, 'ZAR', '2026-02-01', 'Verified bank evidence', [$second, $first]], [
            $page['items'][0]['batch']['reference'], $page['items'][0]['batch']['expectedMinor'], $page['items'][0]['batch']['actualMinor'], $page['items'][0]['batch']['currency'],
            $page['items'][0]['batch']['reconciledOn'], $page['items'][0]['batch']['reason'], $page['items'][0]['batch']['giftIds'],
        ]);
        self::assertSame([2500, 10000, 1, true], [$page['items'][0]['reversedMinor'], $page['items'][0]['currentNetMinor'], $page['items'][0]['version'], $page['items'][0]['needsReview']]);
        self::assertSame(['id', 'batchId', 'version', 'outcome', 'note', 'reversedMinor', 'createdBy', 'createdAt'], array_keys($page['items'][0]['review']));
        self::assertSame(['FOLLOW_UP', 'Waiting for the bank', 2500], [$page['items'][0]['review']['outcome'], $page['items'][0]['review']['note'], $page['items'][0]['review']['reversedMinor']]);
        $this->call('GET', '/api/giving/reconciliations?offset=1', $this->reader);
        self::assertSame([$older, 0, null, false], [$this->json()['items'][0]['batch']['id'], $this->json()['items'][0]['version'], $this->json()['items'][0]['review'], $this->json()['items'][0]['needsReview']]);

        $this->call('GET', "/api/giving/reconciliations/$batch", $this->reader);
        $detail = $this->json();
        self::assertSame(['summary', 'items', 'reviews'], array_keys($detail));
        self::assertSame($page['items'][0], $detail['summary']);
        self::assertSame([
            ['giftId' => $second, 'donorName' => 'Anonymous', 'receivedOn' => '2026-01-05', 'amountMinor' => 2500, 'reversed' => true, 'refundedMinor' => 0],
            ['giftId' => $first, 'donorName' => 'Anonymous', 'receivedOn' => '2026-01-06', 'amountMinor' => 10000, 'reversed' => false, 'refundedMinor' => 0],
        ], $detail['items']);
        self::assertSame([$detail['summary']['review']], $detail['reviews']);
        $this->call('GET', "/api/giving/gifts/$first", $this->reader);
        self::assertSame(200, $this->code());
        $this->call('GET', '/api/giving/gifts?from=2026-01-01&to=2026-01-31', $this->reader);
        self::assertTrue($this->json()['items'][0]['reconciled']);
    }

    public function testCreationChecksDatesCurrencyAndRequestIds(): void
    {
        $gift = $this->gift();
        $body = self::batch([$gift], 10000, 'BANK-001');
        foreach ([
            [['reconciledOn' => '2026-01-04'], 409, 'Every selected gift must be unreconciled, unreversed, in the batch currency and received by the reconciliation date.'],
            [['reconciledOn' => '2999-01-01'], 400, 'Choose a date from 1900 through today.'],
            // Java checks each gift against the batch currency before the currency rule, so an unsupported one is a 409 too.
            [['currency' => 'JPY'], 409, 'Every selected gift must be unreconciled, unreversed, in the batch currency and received by the reconciliation date.'],
            [['currency' => 'USD'], 409, 'Every selected gift must be unreconciled, unreversed, in the batch currency and received by the reconciliation date.'],
            [['giftIds' => [$gift, strtoupper($gift)]], 400, 'Select 1–100 distinct gifts.'],
            [['giftIds' => [Uuid::v4()->toRfc4122()]], 404, 'The Giving record was not found.'],
        ] as [$change, $status, $message]) {
            $this->call('POST', '/api/giving/reconciliations', $this->manager, $change + $body);
            self::assertSame([$status, $message], [$this->code(), $this->message()], (string) json_encode($change));
        }
        $this->created($body);
        $this->call('POST', '/api/giving/reconciliations', $this->manager, ['expectedMinor' => 9000, 'reason' => 'Short'] + $body);
        self::assertSame([409, 'This request identifier was used for different details.'], [$this->code(), $this->message()]);
        $this->call('POST', '/api/giving/reconciliations', $this->manager, self::batch([$this->gift(500)], 500, ' bank-001 '));
        self::assertSame(409, $this->code(), 'references are unique, ignoring case and spaces');
        self::assertSame(1, $this->rows('giving_reconciliation_batches'));
        self::assertSame(1, $this->rows('giving_history', "action = 'BATCH_CREATED'"));
    }

    public function testAFailedReviewRollsBackTheWholeCommand(): void
    {
        $batch = $this->created(self::batch([$this->gift()], 10000, 'BANK-001'));
        // The audit row is the last write; making it fail proves the review insert rolls back with it.
        $this->connection->executeStatement("CREATE FUNCTION test_reject_review_audit() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'audit unavailable'; END $$");
        $this->connection->executeStatement("CREATE TRIGGER test_reject_review_audit BEFORE INSERT ON giving_history FOR EACH ROW WHEN (NEW.action = 'BATCH_REVIEWED') EXECUTE FUNCTION test_reject_review_audit()");
        try {
            $this->call('POST', "/api/giving/reconciliations/$batch/reviews", $this->manager, self::review(0));
            self::assertSame(500, $this->code());
        } finally {
            $this->connection->executeStatement('DROP TRIGGER test_reject_review_audit ON giving_history');
            $this->connection->executeStatement('DROP FUNCTION test_reject_review_audit()');
        }
        self::assertSame(0, $this->rows('giving_reconciliation_reviews'));
        self::assertSame(0, $this->summary($batch)['version']);
        $this->reviewed($batch, self::review(0));
    }

    public function testUnknownBatchesAreNotFound(): void
    {
        $unknown = '00000000-0000-4000-8000-0000000000ff';
        $this->call('GET', "/api/giving/reconciliations/$unknown", $this->reader);
        self::assertSame([404, 'The Giving record was not found.'], [$this->code(), $this->message()]);
        $this->call('POST', "/api/giving/reconciliations/$unknown/reviews", $this->manager, self::review(0));
        self::assertSame([404, 'The Giving record was not found.'], [$this->code(), $this->message()]);
    }

    public function testMalformedInputIsRejected(): void
    {
        $gift = $this->gift();
        $body = self::batch([$gift], 10000, 'BANK-001');
        foreach ([
            ['requestId' => null], ['requestId' => 'nope'], ['reference' => ''], ['reference' => str_repeat('r', 201)], ['expectedMinor' => 100.5], ['expectedMinor' => '10000'],
            ['expectedMinor' => 0], ['expectedMinor' => 100_000_000_000_001], ['expectedMinor' => null], ['currency' => ''], ['reconciledOn' => null],
            ['reconciledOn' => '01/02/2026'], ['reason' => str_repeat('r', 1001)], ['giftIds' => null], ['giftIds' => []], ['giftIds' => ['nope']], ['giftIds' => [null]],
            ['giftIds' => array_map(static fn (): string => Uuid::v4()->toRfc4122(), range(1, 101))],
        ] as $change) {
            $this->call('POST', '/api/giving/reconciliations', $this->manager, array_replace($body, $change));
            self::assertSame(400, $this->code(), (string) json_encode($change));
        }
        $batch = $this->created($body);
        foreach ([['requestId' => 'nope'], ['expectedVersion' => -1], ['expectedVersion' => null], ['outcome' => ''], ['outcome' => 'DONE'], ['note' => ''], ['note' => str_repeat('n', 1001)]] as $change) {
            $this->call('POST', "/api/giving/reconciliations/$batch/reviews", $this->manager, array_filter(array_replace(self::review(0), $change), static fn (mixed $v): bool => null !== $v));
            self::assertSame(400, $this->code(), (string) json_encode($change));
        }
        foreach (['GET /api/giving/reconciliations/nope', 'POST /api/giving/reconciliations/nope/reviews'] as $route) {
            [$method, $uri] = explode(' ', $route);
            $this->call($method, $uri, $this->manager, 'POST' === $method ? self::review(0) : null);
            self::assertSame(400, $this->code(), $route);
        }
        foreach (['limit=0', 'limit=101', 'offset=-1', 'offset=100001', 'limit=x'] as $query) {
            $this->call('GET', "/api/giving/reconciliations?$query", $this->reader);
            self::assertSame(400, $this->code(), $query);
        }
        self::assertSame(1, $this->rows('giving_reconciliation_batches'));
        self::assertSame(0, $this->rows('giving_reconciliation_reviews'));
    }

    public function testReconciliationNeedsItsOwnPermission(): void
    {
        $gift = $this->gift();
        $batch = $this->created(self::batch([$gift], 10000, 'BANK-001'));

        $this->call('GET', '/api/giving/reconciliations');
        self::assertSame(401, $this->code());
        $this->call('POST', '/api/giving/reconciliations', null, self::batch([$gift], 10000, 'BANK-002'));
        self::assertSame(401, $this->code());

        $this->accountWith('bookkeeper', ['giving.manage', 'giving.read', 'giving.reverse', 'giving.acknowledge', 'giving.export']);
        $bookkeeper = $this->login('bookkeeper');
        foreach ([$bookkeeper, $this->reader] as $token) {
            $this->call('POST', '/api/giving/reconciliations', $token, self::batch([$this->gift(300)], 300, 'BANK-X'));
            self::assertSame(403, $this->code());
            $this->call('POST', "/api/giving/reconciliations/$batch/reviews", $token, self::review(0));
            self::assertSame(403, $this->code());
        }
        $this->call('GET', "/api/giving/reconciliations/$batch", $bookkeeper);
        self::assertSame(200, $this->code());

        $this->accountWith('pastor', ['people.manage', 'people.read']);
        $pastor = $this->login('pastor');
        foreach (['/api/giving/reconciliations', "/api/giving/reconciliations/$batch"] as $uri) {
            $this->call('GET', $uri, $pastor);
            self::assertSame(403, $this->code(), 'People permissions grant no Giving access: '.$uri);
        }
        self::assertSame(1, $this->rows('giving_reconciliation_batches'));
        self::assertSame(0, $this->rows('giving_reconciliation_reviews'));
    }
}
