<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\Persistence;

use App\Giving\Domain\Acknowledgement;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Repository\AcknowledgementRepository;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\ParameterType;

/**
 * Acknowledgements and their frozen lines. The number comes from the `number` BIGSERIAL, so sequences may have gaps.
 * Every integrity failure (SQLSTATE class 23) becomes Java's evidence conflict.
 */
final readonly class DbalAcknowledgementStore implements AcknowledgementRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function issuedBy(string $requestId, string $hash): ?string
    {
        $saved = $this->connection->createQueryBuilder()
            ->select('request_hash')
            ->from('giving_acknowledgements')
            ->where('id = :id')
            ->setParameter('id', $requestId)
            ->fetchOne();
        if (false === $saved) {
            return null;
        }
        if ($saved !== $hash) {
            throw GivingException::conflict('This request identifier was already used.');
        }

        return $requestId;
    }

    public function forGift(string $giftId): ?string
    {
        $id = $this->connection->createQueryBuilder()
            ->select('id')
            ->from('giving_acknowledgements')
            ->where('gift_id = :gift')
            ->setParameter('gift', $giftId)
            ->fetchOne();

        return false === $id ? null : (string) $id;
    }

    public function insert(Acknowledgement $acknowledgement, string $hash): void
    {
        try {
            $this->connection->insert('giving_acknowledgements', [
                'id' => $acknowledgement->id,
                'gift_id' => $acknowledgement->giftId,
                'donor_name' => $acknowledgement->donorName,
                'issued_on' => $acknowledgement->issuedOn->iso(),
                'created_by' => $acknowledgement->createdBy,
                'created_at' => Timestamps::toDatabase($acknowledgement->createdAt),
                'request_hash' => $hash,
            ]);
            foreach ($acknowledgement->lines as $line) {
                $this->connection->insert('giving_acknowledgement_lines', [
                    'acknowledgement_id' => $acknowledgement->id,
                    'fund_id' => $line->fundId,
                    'fund_name' => $line->fundName,
                    'amount_minor' => $line->amountMinor,
                ], ['amount_minor' => ParameterType::INTEGER]);
            }
            // As for gifts: run the deferred balance triggers now so a mismatch is translated and rolls back with the
            // command, then restore deferral for any later acknowledgement in the same transaction.
            $this->connection->executeStatement('SET CONSTRAINTS giving_ack_balance, giving_ack_line_balance IMMEDIATE');
            $this->connection->executeStatement('SET CONSTRAINTS giving_ack_balance, giving_ack_line_balance DEFERRED');
        } catch (DriverException $error) {
            if (str_starts_with((string) $error->getSQLState(), '23')) {
                throw GivingException::evidenceConflict();
            }
            throw $error;
        }
    }
}
