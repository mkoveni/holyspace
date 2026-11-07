<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\Persistence;

use App\Giving\Domain\DonorLink;
use App\Giving\Domain\GivingException;
use App\Giving\Domain\Repository\DonorLinkRepository;
use App\Shared\Infrastructure\Persistence\Timestamps;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\Uid\Uuid;

/**
 * Donor links (one per account and one per person) and their append-only history. A person already linked to another
 * account violates the unique person_id, which becomes Java's evidence conflict.
 */
final readonly class DbalDonorLinkStore implements DonorLinkRepository
{
    public function __construct(private Connection $connection)
    {
    }

    public function lock(string $accountId): ?DonorLink
    {
        $row = $this->connection->createQueryBuilder()
            ->select('account_id', 'person_id', 'active', 'version', 'verified_by', 'verified_at')
            ->from('giving_donor_links')
            ->where('account_id = :account')
            ->setParameter('account', $accountId)
            ->forUpdate()
            ->fetchAssociative();

        return false === $row ? null : DonorLink::reconstitute(
            (string) $row['account_id'],
            (string) $row['person_id'],
            (bool) $row['active'],
            (int) $row['version'],
            (string) $row['verified_by'],
            Timestamps::fromDatabase((string) $row['verified_at']),
        );
    }

    public function save(DonorLink $link, string $evidence): void
    {
        $verifiedAt = Timestamps::toDatabase($link->verifiedAt);
        try {
            // The person never changes on update: DonorLink::revise refuses a reassignment.
            $this->connection->executeStatement(
                'INSERT INTO giving_donor_links (account_id, person_id, active, version, verified_by, verified_at) VALUES (?, ?, ?, ?, ?, ?)
                 ON CONFLICT (account_id) DO UPDATE SET active = EXCLUDED.active, version = EXCLUDED.version,
                     verified_by = EXCLUDED.verified_by, verified_at = EXCLUDED.verified_at',
                [$link->accountId, $link->personId, $link->active, $link->version, $link->verifiedBy, $verifiedAt],
                [2 => ParameterType::BOOLEAN, 3 => ParameterType::INTEGER],
            );
            $this->connection->insert('giving_donor_link_history', [
                'id' => Uuid::v4()->toRfc4122(),
                'account_id' => $link->accountId,
                'person_id' => $link->personId,
                'active' => $link->active,
                'version' => $link->version,
                'actor_id' => $link->verifiedBy,
                'evidence' => $evidence,
                'occurred_at' => $verifiedAt,
            ], ['active' => ParameterType::BOOLEAN, 'version' => ParameterType::INTEGER]);
        } catch (DriverException $error) {
            if (str_starts_with((string) $error->getSQLState(), '23')) {
                throw GivingException::evidenceConflict();
            }
            throw $error;
        }
    }
}
