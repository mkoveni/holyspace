<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\Persistence;

use App\Giving\Domain\Allocation;
use App\Giving\Domain\Gift;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Money;
use App\Giving\Domain\Repository\GiftRepository;
use App\Giving\Domain\Reversal;
use App\Shared\Domain\CalendarDate;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Closure;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\ParameterType;

/**
 * Gifts, allocations and reversals. Every integrity failure (SQLSTATE class 23: unique references, the balance and
 * immutability triggers, foreign keys) becomes Java's evidence conflict, as Java's DataIntegrityViolationException handler does.
 */
final readonly class DbalGiftStore implements GiftRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function findForUpdate(string $id): ?Gift
    {
        $row = $this->connection->createQueryBuilder()
            ->select('id', 'donor_id', 'amount_minor', 'currency', 'received_on', 'method', 'external_reference', 'created_by', 'created_at')
            ->from('giving_gifts')
            ->where('id = :id')
            ->setParameter('id', $id)
            ->forUpdate()
            ->fetchAssociative();
        if (false === $row) {
            return null;
        }
        $allocations = $this->connection->createQueryBuilder()
            ->select('fund_id', 'amount_minor')
            ->from('giving_allocations')
            ->where('gift_id = :id')
            ->setParameter('id', $id)
            ->orderBy('fund_id')
            ->fetchAllAssociative();

        return Gift::reconstitute(
            (string) $row['id'],
            null === $row['donor_id'] ? null : (string) $row['donor_id'],
            Money::of((int) $row['amount_minor'], (string) $row['currency']),
            CalendarDate::parse((string) $row['received_on']),
            (string) $row['method'],
            null === $row['external_reference'] ? null : (string) $row['external_reference'],
            array_values(array_map(static fn (array $a): Allocation => new Allocation((string) $a['fund_id'], (int) $a['amount_minor']), $allocations)),
            (string) $row['created_by'],
            Timestamps::fromDatabase((string) $row['created_at']),
        );
    }

    public function postedBy(string $requestId, string $hash): ?string
    {
        return $this->request('giving_gifts', $requestId, $hash);
    }

    public function reversedBy(string $requestId, string $hash): ?string
    {
        return $this->request('giving_reversals', $requestId, $hash);
    }

    public function insert(Gift $gift, string $hash): void
    {
        $this->translated(function () use ($gift, $hash): void {
            $this->connection->insert('giving_gifts', [
                'id' => $gift->id,
                'donor_id' => $gift->donorId,
                'amount_minor' => $gift->money->amountMinor,
                'currency' => $gift->money->currency,
                'received_on' => $gift->receivedOn->iso(),
                'method' => $gift->method,
                'external_reference' => $gift->externalReference,
                'request_hash' => $hash,
                'created_by' => $gift->createdBy,
                'created_at' => Timestamps::toDatabase($gift->createdAt),
            ], ['amount_minor' => ParameterType::INTEGER]);
            foreach ($gift->allocations as $allocation) {
                $this->connection->insert('giving_allocations', [
                    'gift_id' => $gift->id,
                    'fund_id' => $allocation->fundId,
                    'amount_minor' => $allocation->amountMinor,
                ], ['amount_minor' => ParameterType::INTEGER]);
            }
            // The balance triggers are deferred to commit, which runs outside the handler; check them now so a
            // violation is translated here and rolls back with the command. Deferral is restored afterwards: IMMEDIATE
            // lasts for the rest of the transaction and would check a second gift's header before its allocations.
            $this->connection->executeStatement('SET CONSTRAINTS giving_header_balance, giving_allocation_balance IMMEDIATE');
            $this->connection->executeStatement('SET CONSTRAINTS giving_header_balance, giving_allocation_balance DEFERRED');
        });
    }

    public function hasReversal(string $giftId): bool
    {
        return false !== $this->connection->createQueryBuilder()
            ->select('1')
            ->from('giving_reversals')
            ->where('gift_id = :gift')
            ->setParameter('gift', $giftId)
            ->fetchOne();
    }

    public function refunded(string $giftId): int
    {
        return (int) $this->connection->createQueryBuilder()
            ->select('COALESCE(SUM(amount_minor), 0)')
            ->from('giving_payment_refunds')
            ->where('gift_id = :gift')
            ->setParameter('gift', $giftId)
            ->fetchOne();
    }

    public function insertReversal(Reversal $reversal, string $hash): void
    {
        $this->translated(function () use ($reversal, $hash): void {
            $this->connection->insert('giving_reversals', [
                'id' => $reversal->id,
                'gift_id' => $reversal->giftId,
                'reversed_on' => $reversal->reversedOn->iso(),
                'reason' => $reversal->reason,
                'request_hash' => $hash,
                'created_by' => $reversal->createdBy,
                'created_at' => Timestamps::toDatabase($reversal->createdAt),
            ]);
        });
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
            throw GivingException::conflict('This request identifier was already used for different details.');
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
