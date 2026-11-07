<?php

declare(strict_types=1);

namespace App\Tests\Giving\Presentation;

use App\Tests\Support\IdentityApiTestCase;
use Symfony\Component\Uid\Uuid;

final class GivingFundsApiTest extends IdentityApiTestCase
{
    private string $manager;
    private string $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountWith('treasurer', ['giving.manage', 'giving.read']);
        $this->accountWith('auditor', ['giving.read']);
        $this->manager = $this->login('treasurer');
        $this->reader = $this->login('auditor');
    }

    private function fund(string $name = 'General', ?string $requestId = null): string
    {
        $this->call('POST', '/api/giving/funds', $this->manager, ['requestId' => $requestId ?? Uuid::v4()->toRfc4122(), 'name' => $name, 'description' => 'Shared needs']);
        self::assertSame(201, $this->code(), $this->message());

        return (string) $this->json()['id'];
    }

    /** @return array<string, mixed> */
    private function edit(int $version, string $name = 'General', bool $active = false): array
    {
        return ['expectedVersion' => $version, 'name' => $name, 'description' => 'Shared needs', 'active' => $active, 'reason' => 'Consolidated funds'];
    }

    public function testFundCreationRetriesAndVersionedArchivalPreserveHistory(): void
    {
        $requestId = strtoupper(Uuid::v4()->toRfc4122());
        $id = $this->fund('General', $requestId);
        self::assertSame(strtolower($requestId), $id);
        self::assertSame($id, $this->fund('General', $requestId), 'an identical retry returns the same fund');
        self::assertSame(1, $this->rows('giving_funds'));
        self::assertSame(1, $this->rows('giving_history', "target_type = 'FUND' AND action = 'FUND_CREATED' AND target_id = '$id'"));

        $this->call('POST', '/api/giving/funds', $this->manager, ['requestId' => Uuid::v4()->toRfc4122(), 'name' => ' general ']);
        self::assertSame(409, $this->code());
        self::assertSame('A fund already uses this name.', $this->message());

        $this->call('PUT', "/api/giving/funds/$id", $this->manager, $this->edit(0));
        self::assertSame(204, $this->code());
        self::assertSame(1, $this->rows('giving_history', "action = 'FUND_UPDATED' AND reason = 'Consolidated funds' AND target_id = '$id'"));
        $this->call('PUT', "/api/giving/funds/$id", $this->manager, $this->edit(0));
        self::assertSame(409, $this->code());

        $this->call('GET', '/api/giving/funds', $this->reader);
        self::assertSame(['items' => [], 'total' => 0], $this->json());
        $this->call('GET', '/api/giving/funds?archived=true', $this->reader);
        self::assertSame(
            ['items' => [['id' => $id, 'name' => 'General', 'description' => 'Shared needs', 'active' => false, 'version' => 1]], 'total' => 1],
            $this->json(),
        );
    }

    public function testReusingARequestIdForDifferentDetailsIsAConflict(): void
    {
        $requestId = Uuid::v4()->toRfc4122();
        $this->fund('General', $requestId);

        $this->call('POST', '/api/giving/funds', $this->manager, ['requestId' => $requestId, 'name' => 'Missions']);
        self::assertSame(409, $this->code());
        self::assertSame('This request identifier was already used for different details.', $this->message());
    }

    public function testRenamingToAnotherFundsNameIsAConflictAndRollsBack(): void
    {
        $id = $this->fund('General');
        $this->fund('Missions');

        $this->call('PUT', "/api/giving/funds/$id", $this->manager, $this->edit(0, ' MISSIONS ', true));
        self::assertSame(409, $this->code());
        self::assertStringStartsWith('This fund name, gift reference, reversal or request conflicts', $this->message());
        self::assertSame(0, (int) $this->connection->fetchOne('SELECT version FROM giving_funds WHERE id = ?', [$id]));
        self::assertSame(0, $this->rows('giving_history', "action = 'FUND_UPDATED'"));
    }

    public function testStaleVersionIsAConflictAndChangesNothing(): void
    {
        $id = $this->fund();
        $this->call('PUT', "/api/giving/funds/$id", $this->manager, $this->edit(5, 'Other'));

        self::assertSame(409, $this->code());
        self::assertSame('This record changed. Refresh before trying again.', $this->message());
        self::assertSame('General', $this->connection->fetchOne('SELECT name FROM giving_funds WHERE id = ?', [$id]));
        self::assertSame(0, $this->rows('giving_history', "action = 'FUND_UPDATED'"));
    }

    public function testSearchIsLiteralCaseInsensitiveAndPaged(): void
    {
        $this->fund('Building_Fund');
        $this->fund('Building Fund');
        $this->fund('Missions');

        $this->call('GET', '/api/giving/funds?search=%20building%20', $this->reader);
        self::assertSame(['Building Fund', 'Building_Fund'], array_column($this->json()['items'], 'name'));
        $this->call('GET', '/api/giving/funds?search=_', $this->reader);
        self::assertSame(['Building_Fund'], array_column($this->json()['items'], 'name'));
        $this->call('GET', '/api/giving/funds?limit=1&offset=1', $this->reader);
        self::assertSame(3, $this->json()['total']);
        self::assertSame(['Building_Fund'], array_column($this->json()['items'], 'name'));
    }

    public function testMalformedInputIsRejected(): void
    {
        foreach ([
            ['name' => 'General'],
            ['requestId' => 'nope', 'name' => 'General'],
            ['requestId' => Uuid::v4()->toRfc4122(), 'name' => ''],
            ['requestId' => Uuid::v4()->toRfc4122(), 'name' => '   '],
            ['requestId' => Uuid::v4()->toRfc4122(), 'name' => str_repeat('a', 101)],
            ['requestId' => Uuid::v4()->toRfc4122(), 'name' => 'General', 'description' => str_repeat('a', 1001)],
        ] as $body) {
            $this->call('POST', '/api/giving/funds', $this->manager, $body);
            self::assertSame(400, $this->code(), (string) json_encode($body));
        }
        self::assertSame(0, $this->rows('giving_funds'));

        $id = $this->fund();
        $body = $this->edit(0);
        unset($body['expectedVersion']);
        $this->call('PUT', "/api/giving/funds/$id", $this->manager, $body);
        self::assertSame(400, $this->code(), 'expectedVersion is required');
        $this->call('PUT', "/api/giving/funds/$id", $this->manager, ['reason' => ''] + $this->edit(0));
        self::assertSame(400, $this->code(), 'a reason is required');
        $this->call('PUT', '/api/giving/funds/not-a-uuid', $this->manager, $this->edit(0));
        self::assertSame(400, $this->code());
        foreach (['limit=0', 'limit=101', 'limit=abc', 'offset=-1', 'offset=100001', 'archived=maybe'] as $query) {
            $this->call('GET', "/api/giving/funds?$query", $this->reader);
            self::assertSame(400, $this->code(), $query);
        }
    }

    public function testUnknownFundIsNotFound(): void
    {
        $this->call('PUT', '/api/giving/funds/00000000-0000-4000-8000-0000000000aa', $this->manager, $this->edit(0));

        self::assertSame(404, $this->code());
        self::assertSame('The Giving record was not found.', $this->message());
    }

    public function testPermissionsAreEnforced(): void
    {
        $this->call('GET', '/api/giving/funds');
        self::assertSame(401, $this->code());
        $this->call('POST', '/api/giving/funds', null, ['requestId' => Uuid::v4()->toRfc4122(), 'name' => 'X']);
        self::assertSame(401, $this->code());

        $this->call('POST', '/api/giving/funds', $this->reader, ['requestId' => Uuid::v4()->toRfc4122(), 'name' => 'X']);
        self::assertSame(403, $this->code());
        $this->call('PUT', '/api/giving/funds/00000000-0000-4000-8000-0000000000aa', $this->reader, $this->edit(0));
        self::assertSame(403, $this->code());

        $this->accountWith('pastor', ['people.manage', 'people.read']);
        $this->call('GET', '/api/giving/funds', $this->login('pastor'));
        self::assertSame(403, $this->code(), 'People permissions grant no Giving access');

        self::assertSame(0, $this->rows('giving_funds'));
    }

    public function testManagingImpliesReading(): void
    {
        $this->accountWith('bookkeeper', ['giving.manage']);
        $this->call('GET', '/api/giving/funds', $this->login('bookkeeper'));

        self::assertSame(200, $this->code());
    }
}
