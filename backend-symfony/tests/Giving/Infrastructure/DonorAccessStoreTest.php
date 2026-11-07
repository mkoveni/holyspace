<?php

declare(strict_types=1);

namespace App\Tests\Giving\Infrastructure;

use App\Giving\Domain\DonorLink;
use App\Giving\Domain\GivingException;
use App\Giving\Infrastructure\Persistence\DbalDonorAccessReader;
use App\Giving\Infrastructure\Persistence\DbalDonorLinkStore;
use App\Giving\Infrastructure\Persistence\DbalFundStore;
use App\Tests\Support\DatabaseTestCase;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\DriverException;

/** Donor links, their immutable history and the online fund catalogue against the real V25 schema. */
final class DonorAccessStoreTest extends DatabaseTestCase
{
    private const string STAFF = '99999999-9999-4999-8999-999999999999';
    private const string ACCOUNT = '00000000-0000-4000-8000-0000000000a1';
    private const string SECOND = '00000000-0000-4000-8000-0000000000a2';
    private const string PERSON = '00000000-0000-4000-8000-0000000000d1';

    private DbalDonorLinkStore $store;
    private DbalDonorAccessReader $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new DbalDonorLinkStore($this->connection);
        $this->reader = new DbalDonorAccessReader($this->connection);
    }

    private function sqlState(callable $statement): string
    {
        try {
            $statement();
        } catch (DriverException $error) {
            return (string) $error->getSQLState();
        }
        self::fail('the database accepted the statement');
    }

    public function testLinksRoundTripWithOneHistoryRowPerChange(): void
    {
        $link = DonorLink::verify(self::ACCOUNT, self::PERSON, self::STAFF, new DateTimeImmutable('2026-01-01T08:00:00Z'));
        $this->store->save($link, 'Verified in person');
        $revoked = $link->revise(self::PERSON, false, 1, self::STAFF, new DateTimeImmutable('2026-01-02T08:00:00Z'));
        $this->store->save($revoked, 'Left the church');

        self::assertEquals($revoked, $this->store->lock(self::ACCOUNT));
        self::assertNull($this->store->lock(self::SECOND));
        $view = $this->reader->link(self::ACCOUNT);
        self::assertNotNull($view);
        self::assertSame([self::PERSON, false, 2], [$view->personId, $view->active, $view->version]);
        self::assertSame(
            [['version' => 1, 'active' => true, 'evidence' => 'Verified in person'], ['version' => 2, 'active' => false, 'evidence' => 'Left the church']],
            $this->connection->fetchAllAssociative('SELECT version, active, evidence FROM giving_donor_link_history ORDER BY version'),
        );
        self::assertSame('23514', $this->sqlState(fn (): int|string => $this->connection->update('giving_donor_link_history', ['evidence' => 'Changed'], ['account_id' => self::ACCOUNT])));
        self::assertSame('23514', $this->sqlState(fn (): int|string => $this->connection->delete('giving_donor_link_history', ['account_id' => self::ACCOUNT])));
    }

    public function testOnePersonHasOneAccountAndLinksAreListedNewestFirst(): void
    {
        $this->store->save(DonorLink::verify(self::ACCOUNT, self::PERSON, self::STAFF, new DateTimeImmutable('2026-01-01T08:00:00Z')), 'Verified');
        $this->store->save(DonorLink::verify(self::SECOND, '00000000-0000-4000-8000-0000000000d2', self::STAFF, new DateTimeImmutable('2026-01-03T08:00:00Z')), 'Verified');

        self::assertSame([self::SECOND, self::ACCOUNT], array_map(static fn ($link): string => $link->accountId, $this->reader->links(10, 0)));
        self::assertSame([self::ACCOUNT], array_map(static fn ($link): string => $link->accountId, $this->reader->links(10, 1)));

        $this->connection->beginTransaction();
        try {
            $this->store->save(DonorLink::verify('00000000-0000-4000-8000-0000000000a3', self::PERSON, self::STAFF, new DateTimeImmutable()), 'Duplicate person');
            self::fail('a person was linked to two accounts');
        } catch (GivingException $error) {
            self::assertStringStartsWith('This fund name, gift reference, reversal or request conflicts', $error->getMessage());
        } finally {
            $this->connection->rollBack();
        }
    }

    public function testTheCatalogueSeparatesManagementFromDonorSelfService(): void
    {
        $funds = new DbalFundStore($this->connection);
        foreach ([['00000000-0000-4000-8000-000000000001', 'Missions', true], ['00000000-0000-4000-8000-000000000002', 'Building', true], ['00000000-0000-4000-8000-000000000003', 'Archived', false]] as [$id, $name, $active]) {
            $this->connection->insert('giving_funds', ['id' => $id, 'name' => $name, 'description' => '', 'active' => $active ? 'true' : 'false', 'request_hash' => str_repeat('a', 64)]);
        }
        $funds->offerOnline('00000000-0000-4000-8000-000000000001', true);
        $funds->offerOnline('00000000-0000-4000-8000-000000000003', true);
        $funds->offerOnline('00000000-0000-4000-8000-000000000003', true);

        self::assertSame(['Archived', 'Building', 'Missions'], array_map(static fn ($fund): string => $fund->name, $this->reader->funds(true)));
        self::assertSame([true, false, true], array_map(static fn ($fund): bool => $fund->enabled, $this->reader->funds(true)));
        self::assertSame(['Missions'], array_map(static fn ($fund): string => $fund->name, $this->reader->funds(false)), 'archived and not offered funds are hidden');
        self::assertTrue($this->reader->enabled('00000000-0000-4000-8000-000000000001'));
        self::assertFalse($this->reader->enabled('00000000-0000-4000-8000-000000000002'));
        self::assertFalse($this->reader->enabled('00000000-0000-4000-8000-000000000003'), 'an archived fund is never enabled');
        $funds->offerOnline('00000000-0000-4000-8000-000000000001', false);
        self::assertFalse($this->reader->enabled('00000000-0000-4000-8000-000000000001'));
        self::assertSame(2, (int) $this->connection->fetchOne('SELECT count(*) FROM giving_online_funds'), 'one row per fund, updated in place');
    }
}
