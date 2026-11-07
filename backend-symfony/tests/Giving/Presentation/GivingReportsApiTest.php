<?php

declare(strict_types=1);

namespace App\Tests\Giving\Presentation;

use App\Tests\Support\IdentityApiTestCase;
use Symfony\Component\Uid\Uuid;

final class GivingReportsApiTest extends IdentityApiTestCase
{
    private string $manager;
    private string $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountWith('treasurer', ['giving.manage', 'giving.read', 'giving.reverse', 'giving.export']);
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

    /**
     * @param list<array{fundId: string, amountMinor: int}>|null $allocations
     * @param array<string, mixed>                               $extra
     */
    private function gift(string $fund, string $receivedOn, int $amount, array $extra = [], ?array $allocations = null): string
    {
        $this->call('POST', '/api/giving/gifts', $this->manager, $extra + [
            'requestId' => Uuid::v4()->toRfc4122(),
            'amountMinor' => $amount,
            'currency' => 'ZAR',
            'receivedOn' => $receivedOn,
            'method' => 'BANK',
            'allocations' => $allocations ?? [['fundId' => $fund, 'amountMinor' => $amount]],
        ]);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    private function reverse(string $gift, string $on): void
    {
        $this->call('POST', "/api/giving/gifts/$gift/reversals", $this->manager, ['requestId' => Uuid::v4()->toRfc4122(), 'reversedOn' => $on, 'reason' => 'Duplicate ledger entry']);
        self::assertSame(201, $this->code(), $this->message());
    }

    /** @return list<mixed> */
    private function totals(string $query, ?string $token = null): array
    {
        $this->call('GET', "/api/giving/totals?$query", $token ?? $this->reader);
        self::assertSame(200, $this->code(), $this->message());

        return array_values($this->json());
    }

    public function testReversalsCountOnTheirOwnDateSoNetCanBeNegative(): void
    {
        $gift = $this->gift($this->fund(), '2026-01-01', 10000);
        $this->reverse($gift, '2026-02-01');

        self::assertSame([['currency' => 'ZAR', 'grossMinor' => '0', 'reversedMinor' => '10000', 'netMinor' => '-10000']], $this->totals('from=2026-02-01&to=2026-02-28'));
        self::assertSame([['currency' => 'ZAR', 'grossMinor' => '10000', 'reversedMinor' => '10000', 'netMinor' => '0']], $this->totals('from=2026-01-01&to=2026-02-28'));
        self::assertSame([], $this->totals('from=2025-01-01&to=2025-12-31'));
    }

    public function testFundTotalsIncludeOnlyTheirAllocationsAndSeparateCurrencies(): void
    {
        $general = $this->fund('General');
        $missions = $this->fund('Missions');
        $this->gift($general, '2026-01-05', 10000, allocations: [['fundId' => $general, 'amountMinor' => 7000], ['fundId' => $missions, 'amountMinor' => 3000]]);
        $this->gift($general, '2026-01-05', 2000, ['currency' => 'USD']);

        self::assertSame([['currency' => 'ZAR', 'grossMinor' => '3000', 'reversedMinor' => '0', 'netMinor' => '3000']], $this->totals("from=2026-01-01&to=2026-01-31&fundId=$missions"));
        self::assertSame(['USD', 'ZAR'], array_column($this->totals('from=2026-01-01&to=2026-01-31'), 'currency'));
        self::assertSame(['2000', '10000'], array_column($this->totals('from=2026-01-01&to=2026-01-31', $this->manager), 'grossMinor'));
        self::assertSame(['USD'], array_column($this->totals('from=2026-01-01&to=2026-01-31&currency=USD'), 'currency'));
    }

    public function testStatementIncludesPeriodReversalsOfEarlierGiftsWithoutMixingCurrencies(): void
    {
        $fund = $this->fund();
        $donor = $this->person('Donor');
        $gift = $this->gift($fund, '2026-01-05', 10000, ['donorId' => $donor, 'externalReference' => 'BANK-1']);
        $this->reverse($gift, '2026-02-01');
        $usd = $this->gift($fund, '2026-02-02', 2000, ['donorId' => $donor, 'currency' => 'USD']);
        $this->gift($fund, '2026-02-03', 500);

        $this->call('GET', "/api/giving/statements/$donor?from=2026-02-01&to=2026-02-28", $this->reader);
        self::assertSame(200, $this->code(), $this->message());
        $statement = $this->json();
        self::assertSame(['donorId', 'donorName', 'from', 'to', 'generatedAt', 'lines', 'totals'], array_keys($statement));
        self::assertSame([$donor, 'Donor Mabasa', '2026-02-01', '2026-02-28'], array_slice(array_values($statement), 0, 4));
        self::assertMatchesRegularExpression('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z\z/', $statement['generatedAt']);
        self::assertSame([
            'giftId' => $gift, 'kind' => 'REVERSAL', 'date' => '2026-02-01', 'donorId' => $donor, 'donorName' => 'Donor Mabasa', 'fundId' => $fund,
            'fundName' => 'General', 'currency' => 'ZAR', 'amountMinor' => -10000, 'method' => 'BANK', 'reference' => 'BANK-1', 'reason' => 'Duplicate ledger entry',
        ], $statement['lines'][0]);
        self::assertSame([$usd, 'GIFT', 2000, null, ''], [$statement['lines'][1]['giftId'], $statement['lines'][1]['kind'], $statement['lines'][1]['amountMinor'], $statement['lines'][1]['reference'], $statement['lines'][1]['reason']]);
        self::assertCount(2, $statement['lines'], 'the anonymous gift is not on the donor\'s statement');
        self::assertSame([
            ['currency' => 'USD', 'grossMinor' => '2000', 'reversedMinor' => '0', 'netMinor' => '2000'],
            ['currency' => 'ZAR', 'grossMinor' => '0', 'reversedMinor' => '10000', 'netMinor' => '-10000'],
        ], $statement['totals']);

        $this->call('GET', '/api/giving/statements/'.strtoupper($donor).'?from=2026-02-01&to=2026-02-28', $this->manager);
        self::assertSame($donor, $this->json()['donorId']);
    }

    public function testAStatementForAnUnknownPersonIsEmptyRatherThanMissing(): void
    {
        $this->call('GET', '/api/giving/statements/00000000-0000-4000-8000-0000000000dd?from=2026-01-01&to=2026-01-31', $this->reader);

        self::assertSame(200, $this->code());
        self::assertSame(['Person unavailable', [], []], [$this->json()['donorName'], $this->json()['lines'], $this->json()['totals']]);
    }

    public function testLedgerCsvIsAnUncachedAttachmentOfTheFilteredAllocations(): void
    {
        $general = $this->fund('General');
        $missions = $this->fund('Missions');
        $gift = $this->gift($general, '2026-01-05', 10000, ['externalReference' => '=CMD()'], [['fundId' => $general, 'amountMinor' => 7000], ['fundId' => $missions, 'amountMinor' => 3000]]);
        $this->reverse($gift, '2026-02-01');

        $this->call('GET', "/api/giving/ledger.csv?from=2026-02-01&to=2026-02-28&fundId=$missions", $this->manager);
        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/csv;charset=UTF-8', $response->headers->get('Content-Type'));
        self::assertSame('attachment; filename="giving-ledger-2026-02-01-2026-02-28.csv"', $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame([
            "\u{FEFF}date,kind,gift_id,donor,fund,currency,amount_minor,method,reference,reason",
            "\"2026-02-01\",\"REVERSAL\",\"$gift\",\"Anonymous\",\"Missions\",\"ZAR\",-3000,\"BANK\",\"'=CMD()\",\"Duplicate ledger entry\"",
            '',
        ], explode("\r\n", (string) $response->getContent()));
    }

    public function testReportsOverTheLineCapAreRefusedNotTruncated(): void
    {
        // 251 gifts of 20 one-cent allocations: 5,020 lines, over the 5,000 cap. Inserted directly for speed.
        $this->connection->transactional(function (): void {
            $this->connection->executeStatement("INSERT INTO giving_funds(id, name, description, request_hash) SELECT gen_random_uuid(), 'Fund '||n, '', repeat('a', 64) FROM generate_series(1, 20) n");
            $this->connection->executeStatement("INSERT INTO giving_gifts(id, amount_minor, currency, received_on, method, request_hash, created_by, created_at)
                SELECT gen_random_uuid(), 20, 'ZAR', '2026-01-05', 'CASH', repeat('a', 64), gen_random_uuid(), now() FROM generate_series(1, 251)");
            $this->connection->executeStatement('INSERT INTO giving_allocations(gift_id, fund_id, amount_minor) SELECT g.id, f.id, 1 FROM giving_gifts g CROSS JOIN giving_funds f');
        });

        $this->call('GET', '/api/giving/ledger.csv?from=2026-01-01&to=2026-01-31', $this->manager);
        self::assertSame(409, $this->code());
        self::assertSame('This report exceeds 5000 allocation lines. Narrow the date range or choose a donor or fund.', $this->message());
        self::assertSame([['currency' => 'ZAR', 'grossMinor' => '5020', 'reversedMinor' => '0', 'netMinor' => '5020']], $this->totals('from=2026-01-01&to=2026-01-31'), 'totals are never capped');
    }

    public function testMalformedInputIsRejected(): void
    {
        $donor = '00000000-0000-4000-8000-0000000000dd';
        foreach (['', 'from=2026-01-01', 'to=2026-01-31', 'from=2026-02-01&to=2026-01-01', 'from=2016-01-01&to=2026-01-31', 'from=x&to=2026-01-31', 'from=1899-12-31&to=1900-01-31',
            'from=2026-01-01&to=2026-01-31&currency=JPY', 'from=2026-01-01&to=2026-01-31&fundId=nope', 'from=2026-01-01&to=2026-01-31&donorId=nope'] as $query) {
            foreach (["/api/giving/totals?$query", "/api/giving/ledger.csv?$query"] as $uri) {
                $this->call('GET', $uri, $this->manager);
                self::assertSame(400, $this->code(), $uri);
            }
        }
        foreach (['/api/giving/statements/nope?from=2026-01-01&to=2026-01-31', "/api/giving/statements/$donor?from=2026-01-01", "/api/giving/statements/$donor?from=2026-02-01&to=2026-01-01", "/api/giving/statements/$donor?from=2026/01/01&to=2026-01-31"] as $uri) {
            $this->call('GET', $uri, $this->manager);
            self::assertSame(400, $this->code(), $uri);
        }
    }

    public function testReportsNeedFinancialPermissionsAndExportItsOwn(): void
    {
        $period = 'from=2026-01-01&to=2026-01-31';
        $statement = "/api/giving/statements/00000000-0000-4000-8000-0000000000dd?$period";
        foreach (["/api/giving/totals?$period", $statement, "/api/giving/ledger.csv?$period"] as $uri) {
            $this->call('GET', $uri);
            self::assertSame(401, $this->code(), $uri);
        }

        $this->accountWith('pastor', ['people.manage', 'people.read']);
        $this->accountWith('exporter', ['giving.export']);
        $pastor = $this->login('pastor');
        foreach (["/api/giving/totals?$period", $statement, "/api/giving/ledger.csv?$period"] as $uri) {
            $this->call('GET', $uri, $pastor);
            self::assertSame(403, $this->code(), $uri);
        }
        $this->call('GET', "/api/giving/ledger.csv?$period", $this->reader);
        self::assertSame(403, $this->code(), 'read alone cannot export');
        $this->call('GET', "/api/giving/ledger.csv?$period", $this->login('exporter'));
        self::assertSame(403, $this->code(), 'export alone cannot read');

        $this->accountWith('bookkeeper', ['giving.manage']);
        $this->call('GET', $statement, $this->login('bookkeeper'));
        self::assertSame(200, $this->code(), 'giving.manage implies giving.read');
    }
}
