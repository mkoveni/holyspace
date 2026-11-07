<?php

declare(strict_types=1);

namespace App\Tests\Shared\Infrastructure\Persistence;

use App\Shared\Application\UnitOfWork;
use App\Shared\Infrastructure\Persistence\AdvisoryLock;
use App\Shared\Infrastructure\Persistence\DbalAuditTable;
use App\Shared\Infrastructure\Persistence\DbalUnitOfWork;
use App\Tests\Support\DatabaseTestCase;
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use LogicException;
use RuntimeException;
use Symfony\Component\Uid\Uuid;

final class UnitOfWorkTest extends DatabaseTestCase
{
    private UnitOfWork $unitOfWork;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unitOfWork = new DbalUnitOfWork($this->connection);
    }

    public function testCommitsAndReturnsTheOperationResult(): void
    {
        $result = $this->unitOfWork->execute(fn (): string => $this->insertRole('committed'));

        self::assertSame('committed', $result);
        self::assertSame(1, $this->roleCount('committed'));
    }

    public function testRollsBackEveryWriteWhenTheOperationThrows(): void
    {
        try {
            $this->unitOfWork->execute(function (): void {
                $this->insertRole('first');
                $this->insertRole('second');

                throw new RuntimeException('boom');
            });
        } catch (RuntimeException $error) {
            self::assertSame('boom', $error->getMessage());
        }

        self::assertSame(0, $this->roleCount('first'));
        self::assertSame(0, $this->roleCount('second'));
    }

    public function testNestedCallsJoinTheOuterTransaction(): void
    {
        try {
            $this->unitOfWork->execute(function (): void {
                $this->unitOfWork->execute(fn (): string => $this->insertRole('inner'));

                throw new RuntimeException('outer fails');
            });
        } catch (RuntimeException) {
        }

        self::assertSame(0, $this->roleCount('inner'));
    }

    public function testAdvisoryLockIsHeldUntilTheTransactionEnds(): void
    {
        $lock = new AdvisoryLock($this->connection);
        $other = DriverManager::getConnection(new DsnParser(['postgresql' => 'pdo_pgsql'])->parse((string) getenv('DATABASE_URL')));

        $this->unitOfWork->execute(function () use ($lock, $other): void {
            $lock->lock(424242);
            self::assertFalse((bool) $other->fetchOne('SELECT pg_try_advisory_lock(?)', [424242]));
        });

        self::assertTrue((bool) $other->fetchOne('SELECT pg_try_advisory_lock(?)', [424242]));
        $other->close();
    }

    public function testAdvisoryLockOutsideATransactionIsRejected(): void
    {
        $this->expectException(LogicException::class);
        new AdvisoryLock($this->connection)->lock(1);
    }

    public function testAuditTableAppendsWithActorAndTarget(): void
    {
        $actor = $this->insertAccount();
        $audit = new DbalAuditTable($this->connection, 'identity_access_audit');
        $target = Uuid::v4()->toRfc4122();

        $audit->append($actor, 'account.created', $target, new DateTimeImmutable('2026-01-02 03:04:05+02:00'));

        $row = $this->connection->fetchAssociative('SELECT * FROM identity_access_audit');
        self::assertIsArray($row);
        self::assertSame('account.created', $row['action']);
        self::assertSame($target, $row['target_id']);
        self::assertSame($actor, $row['actor_id']);
    }

    private function insertRole(string $name): string
    {
        $this->connection->insert('roles', ['id' => Uuid::v4()->toRfc4122(), 'name' => $name]);

        return $name;
    }

    private function roleCount(string $name): int
    {
        return (int) $this->connection->fetchOne('SELECT count(*) FROM roles WHERE name = ?', [$name]);
    }

    private function insertAccount(): string
    {
        $id = Uuid::v4()->toRfc4122();
        $this->connection->insert('user_accounts', [
            'id' => $id, 'username' => 'actor', 'email' => 'actor@example.test', 'display_name' => 'Actor',
            'password_hash' => 'x', 'status' => 'active', 'created_at' => '2026-01-01 00:00:00+00', 'updated_at' => '2026-01-01 00:00:00+00',
        ]);

        return $id;
    }
}
