<?php

declare(strict_types=1);

namespace App\Tests\Giving\Presentation;

use App\Shared\Domain\CalendarDate;
use App\Tests\Support\IdentityApiTestCase;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

final class GivingGiftsApiTest extends IdentityApiTestCase
{
    private string $manager;
    private string $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountWith('treasurer', ['giving.manage', 'giving.read', 'giving.reverse']);
        $this->accountWith('auditor', ['giving.read']);
        $this->manager = $this->login('treasurer');
        $this->reader = $this->login('auditor');
    }

    private static function today(int $plusDays = 0): string
    {
        return CalendarDate::fromInstant(new DateTimeImmutable(), new DateTimeZone('Africa/Johannesburg'))->plusDays($plusDays)->iso();
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

    /** @return array<string, mixed> */
    private function body(string $fund, string $receivedOn = '2026-01-05', int $amount = 10000): array
    {
        return [
            'requestId' => Uuid::v4()->toRfc4122(),
            'amountMinor' => $amount,
            'currency' => 'ZAR',
            'receivedOn' => $receivedOn,
            'method' => 'BANK',
            'allocations' => [['fundId' => $fund, 'amountMinor' => $amount]],
        ];
    }

    /** @param array<string, mixed> $body */
    private function post(array $body, ?string $token = null): void
    {
        $this->call('POST', '/api/giving/gifts', $token ?? $this->manager, $body);
    }

    /** @param array<string, mixed> $body */
    private function posted(array $body): string
    {
        $this->post($body);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    /** @return array<string, mixed> */
    private function reversal(string $on = '2026-02-01', string $reason = 'Duplicate bank entry'): array
    {
        return ['requestId' => Uuid::v4()->toRfc4122(), 'reversedOn' => $on, 'reason' => $reason];
    }

    public function testGiftIdempotencyAndReferencesPreventDuplicateMoney(): void
    {
        $fund = $this->fund();
        $body = ['externalReference' => 'BANK-123', 'requestId' => strtoupper(Uuid::v4()->toRfc4122())] + $this->body($fund, amount: 12345);
        $id = $this->posted($body);
        self::assertSame(strtolower((string) $body['requestId']), $id);
        self::assertSame($id, $this->posted($body), 'an identical retry returns the same gift');

        $this->post(['requestId' => Uuid::v4()->toRfc4122(), 'externalReference' => ' bank-123 '] + $body);
        self::assertSame(409, $this->code());
        self::assertStringStartsWith('This fund name, gift reference, reversal or request conflicts', $this->message());

        $this->post(['method' => 'CASH'] + $body);
        self::assertSame(409, $this->code());
        self::assertSame('This request identifier was already used for different details.', $this->message());

        self::assertSame(1, $this->rows('giving_gifts'));
        self::assertSame(1, $this->rows('giving_allocations'));
        self::assertSame(1, $this->rows('giving_history', "action = 'GIFT_POSTED'"));
    }

    public function testValidatesAllocationBalanceDatesMethodsAndPeople(): void
    {
        $fund = $this->fund();
        $body = $this->body($fund, self::today());
        foreach ([
            [['allocations' => [['fundId' => $fund, 'amountMinor' => 9999]]], 'Fund allocations must equal the full gift amount.'],
            [['allocations' => [['fundId' => $fund, 'amountMinor' => 5000], ['fundId' => strtoupper($fund), 'amountMinor' => 5000]]], 'Choose each fund once with a positive allocation.'],
            [['receivedOn' => self::today(2)], 'Choose a date from 1900 through today.'],
            [['receivedOn' => '1899-12-31'], 'Choose a date from 1900 through today.'],
            [['method' => 'ONLINE'], 'Online gifts require verified Payments confirmation.'],
            [['currency' => 'JPY'], 'Choose ZAR, USD, EUR or GBP. All supported currencies use two decimal places.'],
            [['donorId' => Uuid::v4()->toRfc4122()], 'Choose an available person or record an anonymous gift.'],
        ] as [$change, $message]) {
            $this->post($change + $body);
            self::assertSame(400, $this->code(), $message);
            self::assertSame($message, $this->message());
        }
        self::assertSame(0, $this->rows('giving_gifts'));

        $donor = $this->person('Donor');
        $id = $this->posted(['donorId' => strtoupper($donor)] + $body);
        $this->call('GET', "/api/giving/gifts/$id", $this->reader);
        self::assertSame(200, $this->code());
        self::assertSame('Donor Mabasa', $this->json()['donorName']);
        self::assertSame($donor, $this->json()['gift']['donorId']);
    }

    public function testAnArchivedFundRefusesNewGifts(): void
    {
        $fund = $this->fund();
        $this->call('PUT', "/api/giving/funds/$fund", $this->manager, ['expectedVersion' => 0, 'name' => 'General', 'active' => false, 'reason' => 'Closed']);
        self::assertSame(204, $this->code());

        $this->post($this->body($fund));
        self::assertSame(409, $this->code());
        self::assertSame('An allocated fund is missing or inactive.', $this->message());
        $this->post($this->body(Uuid::v4()->toRfc4122()));
        self::assertSame(409, $this->code());
        self::assertSame(0, $this->rows('giving_gifts'));
    }

    public function testListAndDetailMatchTheJavaShape(): void
    {
        $general = $this->fund('General');
        $missions = $this->fund('Missions');
        $body = $this->body($general, '2026-01-05');
        $body['allocations'] = [['fundId' => $missions, 'amountMinor' => 3000], ['fundId' => $general, 'amountMinor' => 7000]];
        $body['externalReference'] = ' REF-1 ';
        $id = $this->posted($body);
        $usd = $this->posted(['currency' => 'USD'] + $this->body($general, '2026-01-06', 2000));

        $this->call('GET', "/api/giving/gifts?from=2026-01-01&to=2026-01-31&fundId=$missions", $this->reader);
        self::assertSame(200, $this->code());
        self::assertSame(['items' => [[
            'id' => $id,
            'donorId' => null,
            'donorName' => 'Anonymous',
            'amountMinor' => 10000,
            'currency' => 'ZAR',
            'receivedOn' => '2026-01-05',
            'method' => 'BANK',
            'externalReference' => 'REF-1',
            'reversed' => false,
            'reconciled' => false,
            'refundedMinor' => 0,
        ]], 'total' => 1], $this->json());

        $this->call('GET', '/api/giving/gifts?from=2026-01-01&to=2026-01-31&limit=1', $this->reader);
        self::assertSame(2, $this->json()['total']);
        self::assertSame([$usd], array_column($this->json()['items'], 'id'), 'newest received first');
        $this->call('GET', '/api/giving/gifts?from=2026-01-01&to=2026-01-31&currency=USD', $this->reader);
        self::assertSame([$usd], array_column($this->json()['items'], 'id'));
        $this->call('GET', '/api/giving/gifts?from=2026-01-06&to=2026-01-31', $this->reader);
        self::assertSame([$usd], array_column($this->json()['items'], 'id'));

        $this->call('GET', "/api/giving/gifts/$id", $this->reader);
        $detail = $this->json();
        $sorted = strcmp($general, $missions) < 0
            ? [['fundId' => $general, 'amountMinor' => 7000], ['fundId' => $missions, 'amountMinor' => 3000]]
            : [['fundId' => $missions, 'amountMinor' => 3000], ['fundId' => $general, 'amountMinor' => 7000]];
        self::assertSame(['gift', 'donorName', 'fundNames', 'reversal', 'history', 'refundedMinor'], array_keys($detail));
        self::assertSame(['id', 'donorId', 'money', 'receivedOn', 'method', 'externalReference', 'allocations', 'createdBy', 'createdAt'], array_keys($detail['gift']));
        self::assertSame(['amountMinor' => 10000, 'currency' => 'ZAR'], $detail['gift']['money']);
        self::assertSame($sorted, $detail['gift']['allocations']);
        self::assertMatchesRegularExpression('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z\z/', $detail['gift']['createdAt']);
        self::assertEqualsCanonicalizing([$general => 'General', $missions => 'Missions'], $detail['fundNames']);
        self::assertNull($detail['reversal']);
        self::assertSame(['GIFT_POSTED'], array_column($detail['history'], 'action'));
        self::assertSame(0, $detail['refundedMinor']);
    }

    public function testReversalsAreFullImmutableDatedAndSafelyRetried(): void
    {
        $gift = $this->posted($this->body($this->fund(), '2026-01-01'));
        $body = $this->reversal();
        $this->call('POST', "/api/giving/gifts/$gift/reversals", $this->manager, $body);
        self::assertSame(201, $this->code(), $this->message());
        $reversal = (string) $this->json()['id'];
        self::assertSame($body['requestId'], $reversal);
        $this->call('POST', "/api/giving/gifts/$gift/reversals", $this->manager, $body);
        self::assertSame(201, $this->code());
        self::assertSame($reversal, $this->json()['id']);

        $this->call('POST', "/api/giving/gifts/$gift/reversals", $this->manager, $this->reversal('2026-02-02', 'Again'));
        self::assertSame(409, $this->code());
        self::assertSame('This gift already has a full reversal.', $this->message());

        $this->call('GET', "/api/giving/gifts/$gift", $this->reader);
        $detail = $this->json();
        self::assertSame(['id', 'giftId', 'reversedOn', 'reason', 'createdBy', 'createdAt'], array_keys($detail['reversal']));
        self::assertSame([$reversal, $gift, '2026-02-01', 'Duplicate bank entry'], array_slice(array_values($detail['reversal']), 0, 4));
        self::assertSame(['GIFT_POSTED', 'GIFT_REVERSED'], array_column($detail['history'], 'action'));
        self::assertSame(['', 'Duplicate bank entry'], array_column($detail['history'], 'reason'));
        self::assertSame(10000, $detail['gift']['money']['amountMinor']);

        $this->call('GET', '/api/giving/gifts?from=2026-01-01&to=2026-01-31', $this->reader);
        self::assertTrue($this->json()['items'][0]['reversed']);
        self::assertSame(1, $this->rows('giving_reversals'));
        self::assertSame(1, $this->rows('giving_history', "action = 'GIFT_REVERSED'"));
    }

    public function testReversalDatesAreBounded(): void
    {
        $gift = $this->posted($this->body($this->fund(), '2026-01-05'));
        foreach (['2026-01-04' => "A reversal cannot precede the gift's received date.", self::today(2) => 'Choose a date from 1900 through today.'] as $on => $message) {
            $this->call('POST', "/api/giving/gifts/$gift/reversals", $this->manager, $this->reversal((string) $on));
            self::assertSame(400, $this->code());
            self::assertSame($message, $this->message());
        }
        $this->call('POST', '/api/giving/gifts/00000000-0000-4000-8000-0000000000aa/reversals', $this->manager, $this->reversal());
        self::assertSame(404, $this->code());
        self::assertSame('The Giving record was not found.', $this->message());
        self::assertSame(0, $this->rows('giving_reversals'));
        self::assertSame(0, $this->rows('giving_history', "action = 'GIFT_REVERSED'"));
    }

    public function testAggregateNamespacesKeepFundHistoryOutOfGiftEvidence(): void
    {
        $fund = $this->fund();
        $gift = $this->posted(['requestId' => $fund] + $this->body($fund));

        $this->call('GET', "/api/giving/gifts/$gift", $this->reader);
        self::assertSame(['GIFT_POSTED'], array_column($this->json()['history'], 'action'));
    }

    public function testMalformedInputIsRejected(): void
    {
        $fund = $this->fund();
        $body = $this->body($fund, amount: 100);
        foreach ([
            ['amountMinor' => 100.9, 'allocations' => [['fundId' => $fund, 'amountMinor' => 100.9]]],
            ['amountMinor' => 100.9],
            ['allocations' => [['fundId' => $fund, 'amountMinor' => 100.9]]],
            ['allocations' => [null]],
            ['allocations' => []],
            ['allocations' => array_fill(0, 21, ['fundId' => $fund, 'amountMinor' => 1])],
            ['allocations' => [['fundId' => 'nope', 'amountMinor' => 100]]],
            ['allocations' => [['amountMinor' => 100]]],
            ['allocations' => null],
            ['amountMinor' => 0],
            ['amountMinor' => 1_000_000_000_001],
            ['amountMinor' => '100'],
            ['requestId' => null],
            ['requestId' => 'nope'],
            ['donorId' => 'nope'],
            ['currency' => ''],
            ['method' => ''],
            ['receivedOn' => null],
            ['receivedOn' => '05/01/2026'],
            ['externalReference' => str_repeat('r', 201)],
        ] as $change) {
            $this->post(array_replace($body, $change));
            self::assertSame(400, $this->code(), (string) json_encode($change));
        }
        self::assertSame(0, $this->rows('giving_gifts'));

        $gift = $this->posted($body);
        foreach ([['requestId' => 'nope'], ['reversedOn' => null], ['reversedOn' => '2026-1-1'], ['reason' => ''], ['reason' => str_repeat('r', 1001)]] as $change) {
            $this->call('POST', "/api/giving/gifts/$gift/reversals", $this->manager, array_replace($this->reversal(), $change));
            self::assertSame(400, $this->code(), (string) json_encode($change));
        }
        foreach (['GET /api/giving/gifts/nope', 'POST /api/giving/gifts/nope/reversals'] as $route) {
            [$method, $uri] = explode(' ', $route);
            $this->call($method, $uri, $this->manager, 'POST' === $method ? $this->reversal() : null);
            self::assertSame(400, $this->code(), $route);
        }
        foreach (['', 'from=2026-01-01', 'from=2026-02-01&to=2026-01-01', 'from=2016-01-01&to=2026-01-31', 'from=2026-01-01&to=2026-01-31&limit=101',
            'from=2026-01-01&to=2026-01-31&currency=JPY', 'from=2026-01-01&to=2026-01-31&fundId=nope', 'from=x&to=2026-01-31'] as $query) {
            $this->call('GET', "/api/giving/gifts?$query", $this->reader);
            self::assertSame(400, $this->code(), $query);
        }
        self::assertSame(0, $this->rows('giving_reversals'));
    }

    public function testUnknownGiftIsNotFound(): void
    {
        $this->call('GET', '/api/giving/gifts/00000000-0000-4000-8000-0000000000aa', $this->reader);

        self::assertSame(404, $this->code());
        self::assertSame('The Giving record was not found.', $this->message());
    }

    public function testFinancialReadAndReversalPermissionsRemainIndependent(): void
    {
        $fund = $this->fund();
        $gift = $this->posted($this->body($fund));

        $this->call('GET', '/api/giving/gifts?from=2026-01-01&to=2026-01-31');
        self::assertSame(401, $this->code());
        $this->call('POST', '/api/giving/gifts', null, $this->body($fund));
        self::assertSame(401, $this->code());

        $this->post($this->body($fund), $this->reader);
        self::assertSame(403, $this->code());
        $this->call('POST', "/api/giving/gifts/$gift/reversals", $this->reader, $this->reversal());
        self::assertSame(403, $this->code());

        $this->accountWith('bookkeeper', ['giving.manage']);
        $bookkeeper = $this->login('bookkeeper');
        $this->call('GET', "/api/giving/gifts/$gift", $bookkeeper);
        self::assertSame(200, $this->code(), 'managing implies reading');
        $this->call('POST', "/api/giving/gifts/$gift/reversals", $bookkeeper, $this->reversal());
        self::assertSame(403, $this->code(), 'reversing needs giving.reverse on its own');

        $this->accountWith('pastor', ['people.manage', 'people.read']);
        $this->call('GET', "/api/giving/gifts/$gift", $this->login('pastor'));
        self::assertSame(403, $this->code(), 'People permissions grant no Giving access');

        self::assertSame(1, $this->rows('giving_gifts'));
        self::assertSame(0, $this->rows('giving_reversals'));
    }
}
