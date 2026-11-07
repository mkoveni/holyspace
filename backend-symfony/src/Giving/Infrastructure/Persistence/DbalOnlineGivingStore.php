<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\Persistence;

use App\Giving\Domain\GivingException;
use App\Giving\Domain\OnlineRefund;
use App\Giving\Domain\PledgeAssignment;
use App\Giving\Domain\Repository\OnlineGivingRepository;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\ParameterType;

/**
 * Payments evidence behind online gifts. Integrity failures (SQLSTATE class 23: unique charge and references, the refund
 * guard and online-source triggers, foreign keys) and data failures (class 22: a reference over 200 characters) become
 * Java's evidence conflict, as Spring's DataIntegrityViolationException covers both classes.
 */
final readonly class DbalOnlineGivingStore implements OnlineGivingRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function source(string $giftId, string $chargeId, string $reference): void
    {
        $this->translated(function () use ($giftId, $chargeId, $reference): void {
            $this->connection->insert('giving_payment_sources', ['gift_id' => $giftId, 'charge_id' => $chargeId, 'reference' => $reference]);
            // The online-source check is deferred to commit, which runs outside the caller; check it now so a violation is
            // translated here, then restore deferral for any later gift in the same transaction (as DbalGiftStore does).
            $this->connection->executeStatement('SET CONSTRAINTS giving_online_source IMMEDIATE');
            $this->connection->executeStatement('SET CONSTRAINTS giving_online_source DEFERRED');
        });
    }

    public function refundExists(string $id, string $giftId, int $amountMinor, string $reference, CalendarDate $refundedOn): bool
    {
        $row = $this->connection->createQueryBuilder()
            ->select('gift_id', 'amount_minor', 'reference', 'refunded_on')
            ->from('giving_payment_refunds')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->fetchAssociative();
        if (false === $row) {
            return false;
        }
        if ((string) $row['gift_id'] !== $giftId || (int) $row['amount_minor'] !== $amountMinor || (string) $row['reference'] !== $reference
            || (string) $row['refunded_on'] !== $refundedOn->iso()) {
            throw GivingException::conflict('Refund identity conflicts with existing evidence.');
        }

        return true;
    }

    public function refund(OnlineRefund $refund): void
    {
        $this->translated(function () use ($refund): void {
            $this->connection->insert('giving_payment_refunds', [
                'id' => $refund->id,
                'gift_id' => $refund->giftId,
                'fund_id' => $refund->fundId,
                'amount_minor' => $refund->amountMinor,
                'reference' => $refund->reference,
                'refunded_on' => $refund->refundedOn->iso(),
                'created_by' => $refund->createdBy,
                'created_at' => Timestamps::toDatabase($refund->createdAt),
            ], ['amount_minor' => ParameterType::INTEGER]);
        });
    }

    public function activeAssignments(string $giftId): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from('giving_pledge_assignments')
            ->where('gift_id = :gift')
            ->andWhere("status = 'ACTIVE'")
            ->setParameter('gift', $giftId)
            ->orderBy('pledge_id')
            ->addOrderBy('id')
            ->fetchAllAssociative();

        return array_values(array_map(static fn (array $row): PledgeAssignment => PledgeAssignmentRow::toAssignment($row), $rows));
    }

    private function translated(Closure $write): void
    {
        try {
            $write();
        } catch (DriverException $error) {
            $state = (string) $error->getSQLState();
            if (str_starts_with($state, '23') || str_starts_with($state, '22')) {
                throw GivingException::evidenceConflict();
            }
            throw $error;
        }
    }
}
