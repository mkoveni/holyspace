<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Shared;

use App\ChildrenMinistry\Shared\Infrastructure\Persistence\DbalChildLocks;
use App\ChildrenMinistry\Shared\Infrastructure\Persistence\DbalChildrenAudit;
use App\Tests\Support\IdentityApiTestCase;
use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Component\Uid\Uuid;

final class ChildrenInfrastructureTest extends IdentityApiTestCase
{
    private function child(): string
    {
        $id = Uuid::v4()->toRfc4122();
        $this->connection->insert('people_persons', [
            'id' => $id, 'first_name' => 'Child', 'last_name' => 'Test', 'gender' => 'UNKNOWN',
            'created_at' => '2026-01-01 00:00:00+00', 'updated_at' => '2026-01-01 00:00:00+00',
        ]);

        return $id;
    }

    public function testLockingCreatesTheGuardRowOnceAndHoldsItForTheTransaction(): void
    {
        $child = $this->child();
        $locks = new DbalChildLocks($this->connection);

        $this->connection->transactional(function () use ($locks, $child): void {
            $locks->lock($child);
            $locks->lock($child);
        });

        self::assertSame(1, $this->rows('children_child_guards', "child_id = '$child'"));
    }

    public function testAnUnknownChildCannotBeLocked(): void
    {
        $this->expectException(\Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException::class);

        new DbalChildLocks($this->connection)->lock(Uuid::v4()->toRfc4122());
    }

    public function testAuditRowsCarryActorTargetReasonAndReturnTheirId(): void
    {
        $actor = Uuid::v4()->toRfc4122();
        $target = Uuid::v4()->toRfc4122();
        $audit = new DbalChildrenAudit($this->connection);

        $id = $audit->record($actor, 'consent.revoked', $target, 'Guardian request', new DateTimeImmutable('2026-06-01T10:00:00Z'));
        $plain = $audit->record($actor, 'programme.created', $target, '', new DateTimeImmutable('2026-06-01T10:00:00Z'));

        self::assertNotSame($id, $plain);
        self::assertSame(1, $this->rows('children_audit', "id = '$id' AND actor_id = '$actor' AND target_id = '$target' AND action = 'consent.revoked' AND reason = 'Guardian request'"));
        self::assertSame(1, $this->rows('children_audit', "id = '$plain' AND reason = ''"));
    }

    public function testTheGuardRowReallyBlocksAnotherConnectionUntilTheHolderFinishes(): void
    {
        $child = $this->child();
        $locks = new DbalChildLocks($this->connection);
        $locks->lock($child); // creates and commits the guard row so the contest below is about the row lock itself

        $other = DriverManager::getConnection(new DsnParser(['postgresql' => 'pdo_pgsql'])->parse((string) getenv('DATABASE_URL')));
        try {
            $this->connection->beginTransaction();
            $locks->lock($child);

            $other->executeStatement("SET lock_timeout = '300ms'");
            try {
                new DbalChildLocks($other)->lock($child);
                self::fail('A second transaction must wait for the first to release the child.');
            } catch (DriverException $timeout) {
                self::assertSame('55P03', $timeout->getSQLState(), 'lock_not_available: the second connection waited and gave up');
            }

            $this->connection->rollBack();
            new DbalChildLocks($other)->lock($child);
            $this->addToAssertionCount(1);
        } finally {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            $other->close();
        }
    }
}
