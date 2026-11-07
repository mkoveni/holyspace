<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\Persistence;

use App\Giving\Domain\GivingException;
use App\Giving\Domain\Money;
use App\Giving\Domain\Pledge;
use App\Giving\Domain\PledgeAssignment;
use App\Giving\Domain\Repository\PledgeRepository;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\ParameterType;

/**
 * Pledges and assignments. Every integrity failure (SQLSTATE class 23: the pledge and assignment guard triggers, foreign
 * keys, a concurrent duplicate id) becomes Java's evidence conflict.
 */
final readonly class DbalPledgeStore implements PledgeRepository
{
    private const string ACTIVE_UNREVERSED = "status = 'ACTIVE' AND NOT EXISTS (SELECT 1 FROM giving_reversals r WHERE r.gift_id = giving_pledge_assignments.gift_id)";

    public function __construct(private Connection $connection)
    {
    }

    public function pledgedBy(string $requestId, string $hash): ?string
    {
        return $this->request('giving_pledges', $requestId, $hash);
    }

    public function insert(Pledge $pledge, string $hash): void
    {
        $this->translated(function () use ($pledge, $hash): void {
            $this->connection->insert('giving_pledges', [
                'id' => $pledge->id,
                'donor_id' => $pledge->donorId,
                'fund_id' => $pledge->fundId,
                'amount_minor' => $pledge->target->amountMinor,
                'currency' => $pledge->target->currency,
                'starts_on' => $pledge->startsOn->iso(),
                'ends_on' => $pledge->endsOn->iso(),
                'status' => $pledge->status,
                'version' => $pledge->version,
                'request_hash' => $hash,
                'created_by' => $pledge->createdBy,
                'created_at' => Timestamps::toDatabase($pledge->createdAt),
            ], ['amount_minor' => ParameterType::INTEGER, 'version' => ParameterType::INTEGER]);
        });
    }

    public function findForUpdate(string $id): ?Pledge
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'donor_id', 'fund_id', 'amount_minor', 'currency', 'starts_on', 'ends_on', 'status', 'version', 'cancellation_reason', 'created_by', 'created_at')
            ->from('giving_pledges')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->forUpdate()
            ->fetchAssociative();

        return false === $row ? null : Pledge::reconstitute(
            (string) $row['id'],
            (string) $row['donor_id'],
            (string) $row['fund_id'],
            Money::of((int) $row['amount_minor'], (string) $row['currency']),
            CalendarDate::parse((string) $row['starts_on']),
            CalendarDate::parse((string) $row['ends_on']),
            (string) $row['status'],
            (int) $row['version'],
            null === $row['cancellation_reason'] ? null : (string) $row['cancellation_reason'],
            (string) $row['created_by'],
            Timestamps::fromDatabase((string) $row['created_at']),
        );
    }

    public function save(Pledge $pledge): void
    {
        $changed = 0;
        $this->translated(function () use ($pledge, &$changed): void {
            $changed = $this->connection->update('giving_pledges', [
                'status' => $pledge->status,
                'version' => $pledge->version,
                'cancellation_reason' => $pledge->cancellationReason,
            ], ['id' => $pledge->id, 'version' => $pledge->version - 1], ['version' => ParameterType::INTEGER]);
        });
        if (1 !== $changed) {
            throw GivingException::conflict('The pledge changed. Refresh before trying again.');
        }
    }

    public function assignedBy(string $requestId, string $hash): ?string
    {
        return $this->request('giving_pledge_assignments', $requestId, $hash);
    }

    public function findAssignment(string $id): ?PledgeAssignment
    {
        $row = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('giving_pledge_assignments')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->fetchAssociative();

        return false === $row ? null : PledgeAssignmentRow::toAssignment($row);
    }

    public function insertAssignment(PledgeAssignment $assignment, string $hash): void
    {
        $this->translated(function () use ($assignment, $hash): void {
            $this->connection->insert('giving_pledge_assignments', [
                'id' => $assignment->id,
                'pledge_id' => $assignment->pledgeId,
                'gift_id' => $assignment->giftId,
                'fund_id' => $assignment->fundId,
                'donor_id' => $assignment->donorId,
                'currency' => $assignment->currency,
                'amount_minor' => $assignment->amountMinor,
                'status' => $assignment->status,
                'version' => $assignment->version,
                'request_hash' => $hash,
                'created_by' => $assignment->createdBy,
                'created_at' => Timestamps::toDatabase($assignment->createdAt),
            ], ['amount_minor' => ParameterType::INTEGER, 'version' => ParameterType::INTEGER]);
        });
    }

    public function saveAssignment(PledgeAssignment $assignment): void
    {
        $changed = 0;
        $this->translated(function () use ($assignment, &$changed): void {
            $changed = $this->connection->update('giving_pledge_assignments', [
                'status' => $assignment->status,
                'version' => $assignment->version,
                'cancellation_reason' => $assignment->cancellationReason,
                'cancelled_by' => $assignment->cancelledBy,
                'cancelled_at' => null === $assignment->cancelledAt ? null : Timestamps::toDatabase($assignment->cancelledAt),
            ], ['id' => $assignment->id, 'version' => $assignment->version - 1], ['version' => ParameterType::INTEGER]);
        });
        if (1 !== $changed) {
            throw GivingException::conflict('This assignment changed.');
        }
    }

    public function credited(string $pledgeId): int
    {
        return (int) $this->connection->createQueryBuilder()
            ->select('COALESCE(SUM(amount_minor), 0)')
            ->from('giving_pledge_assignments')
            ->where('pledge_id = :pledge')
            ->andWhere(self::ACTIVE_UNREVERSED)
            ->setParameter('pledge', $pledgeId)
            ->fetchOne();
    }

    public function assigned(string $giftId, string $fundId): int
    {
        return (int) $this->connection->createQueryBuilder()
            ->select('COALESCE(SUM(amount_minor), 0)')
            ->from('giving_pledge_assignments')
            ->where('gift_id = :gift')
            ->andWhere('fund_id = :fund')
            ->andWhere("status = 'ACTIVE'")
            ->setParameter('gift', $giftId)
            ->setParameter('fund', $fundId)
            ->fetchOne();
    }

    private function request(string $table, string $id, string $hash): ?string
    {
        $saved = $this->connection->createQueryBuilder()
            ->select('request_hash')
            ->from($table)
            ->where('id = :id')
            ->setParameter('id', $id)
            ->fetchOne();
        if (false === $saved) {
            return null;
        }
        if ($saved !== $hash) {
            // Java's pledge store words this differently from its gift store ("was used", not "was already used").
            throw GivingException::conflict('This request identifier was used for different details.');
        }

        return $id;
    }

    private function translated(Closure $write): void
    {
        try {
            $write();
        } catch (DriverException $error) {
            if (str_starts_with((string) $error->getSQLState(), '23')) {
                throw GivingException::evidenceConflict();
            }
            throw $error;
        }
    }
}
