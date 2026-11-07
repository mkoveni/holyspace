<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Infrastructure\Persistence;

use App\ChildrenMinistry\Safeguarding\Application\ConsentPolicyView;
use App\ChildrenMinistry\Safeguarding\Application\ConsentView;
use App\ChildrenMinistry\Safeguarding\Application\Contract\SafeguardingReader;
use App\ChildrenMinistry\Safeguarding\Application\GuardianAuthorityView;
use App\ChildrenMinistry\Safeguarding\Application\PickupAuthorizationView;
use App\ChildrenMinistry\Safeguarding\Domain\Consent;
use App\ChildrenMinistry\Safeguarding\Domain\ConsentPolicy;
use App\ChildrenMinistry\Safeguarding\Domain\GuardianAuthority;
use App\ChildrenMinistry\Safeguarding\Domain\PickupAuthorization;
use App\ChildrenMinistry\Safeguarding\Domain\Repository\SafeguardingRepository;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Infrastructure\Persistence\Timestamps;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final readonly class DbalSafeguardingStore implements SafeguardingRepository, SafeguardingReader
{
    private const string AUTHORITIES = 'children_guardian_authorities';
    private const string PICKUPS = 'children_pickup_authorizations';
    private const string CONSENTS = 'children_consents';
    private const string POLICIES = 'children_consent_policies';

    public function __construct(private Connection $connection)
    {
    }

    public function findGuardianAuthority(string $id): ?GuardianAuthority
    {
        return $this->authorityRow($id, false);
    }

    public function guardianAuthorityForUpdate(string $id): ?GuardianAuthority
    {
        return $this->authorityRow($id, true);
    }

    public function insertGuardianAuthority(GuardianAuthority $authority): void
    {
        $this->connection->insert(self::AUTHORITIES, ['id' => $authority->id] + $this->authorityColumns($authority));
    }

    public function updateGuardianAuthority(GuardianAuthority $authority): void
    {
        $changed = $this->connection->update(self::AUTHORITIES, $this->authorityColumns($authority), ['id' => $authority->id, 'version' => $authority->version - 1]);
        if (1 !== $changed) {
            throw ChildrenException::conflict('Record changed. Reload and try again.');
        }
    }

    public function findConsentPolicy(string $id): ?ConsentPolicy
    {
        $view = $this->consentPolicy($id);

        return null === $view ? null : ConsentPolicy::reconstitute($view->id, $view->programmeId, $view->label, $view->documentReference, $view->contentHash, $view->createdAt);
    }

    public function insertConsentPolicy(ConsentPolicy $policy): void
    {
        $this->connection->insert(self::POLICIES, [
            'id' => $policy->id,
            'programme_id' => $policy->programmeId,
            'label' => $policy->label,
            'document_reference' => $policy->documentReference,
            'content_hash' => $policy->contentHash,
            'created_at' => Timestamps::toDatabase($policy->createdAt),
        ]);
    }

    public function consentForUpdate(string $id): ?Consent
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from(self::CONSENTS)->where('id = :id')->setParameter('id', $id)->forUpdate()->fetchAssociative();
        if (false === $row) {
            return null;
        }
        $v = $this->consentView($row);

        return Consent::reconstitute($v->id, $v->programmeId, $v->policyId, $v->childId, $v->authorityId, $v->validFrom, $v->validUntil, $v->acceptedAt, $v->recordedBy, $v->evidenceReference, $v->revokedAt, $v->revocationReason, $v->version);
    }

    public function insertConsent(Consent $consent): void
    {
        $this->connection->insert(self::CONSENTS, ['id' => $consent->id] + $this->consentColumns($consent));
    }

    public function updateConsent(Consent $consent): void
    {
        $changed = $this->connection->update(self::CONSENTS, $this->consentColumns($consent), ['id' => $consent->id, 'version' => $consent->version - 1]);
        if (1 !== $changed) {
            throw ChildrenException::conflict('Record changed. Reload and try again.');
        }
    }

    public function consent(string $id): ?ConsentView
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from(self::CONSENTS)->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : $this->consentView($row);
    }

    public function consentPolicy(string $id): ?ConsentPolicyView
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from(self::POLICIES)->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : new ConsentPolicyView(
            (string) $row['id'],
            (string) $row['programme_id'],
            (string) $row['label'],
            (string) $row['document_reference'],
            (string) $row['content_hash'],
            Timestamps::fromDatabase((string) $row['created_at']),
        );
    }

    public function consents(string $childId): array
    {
        $rows = $this->connection->createQueryBuilder()->select('*')->from(self::CONSENTS)
            ->where('child_id = :child')->setParameter('child', $childId)
            ->orderBy('accepted_at')->addOrderBy('id')->fetchAllAssociative();

        return array_values(array_map($this->consentView(...), $rows));
    }

    /** @return array<string, mixed> */
    private function consentColumns(Consent $consent): array
    {
        return [
            'programme_id' => $consent->programmeId,
            'policy_id' => $consent->policyId,
            'child_id' => $consent->childId,
            'authority_id' => $consent->authorityId,
            'valid_from' => Timestamps::toDatabase($consent->validFrom),
            'valid_until' => Timestamps::toDatabase($consent->validUntil),
            'accepted_at' => Timestamps::toDatabase($consent->acceptedAt),
            'recorded_by' => $consent->recordedBy,
            'evidence_reference' => $consent->evidenceReference,
            'revoked_at' => null === $consent->revokedAt ? null : Timestamps::toDatabase($consent->revokedAt),
            'revocation_reason' => $consent->revocationReason,
            'version' => $consent->version,
        ];
    }

    /** @param array<string, mixed> $row */
    private function consentView(array $row): ConsentView
    {
        return new ConsentView(
            (string) $row['id'],
            (string) $row['programme_id'],
            (string) $row['policy_id'],
            (string) $row['child_id'],
            (string) $row['authority_id'],
            Timestamps::fromDatabase((string) $row['valid_from']),
            Timestamps::fromDatabase((string) $row['valid_until']),
            Timestamps::fromDatabase((string) $row['accepted_at']),
            (string) $row['recorded_by'],
            (string) $row['evidence_reference'],
            self::time($row['revoked_at']),
            null === $row['revocation_reason'] ? null : (string) $row['revocation_reason'],
            (int) $row['version'],
        );
    }

    public function pickupAuthorizationForUpdate(string $id): ?PickupAuthorization
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from(self::PICKUPS)->where('id = :id')->setParameter('id', $id)->forUpdate()->fetchAssociative();

        return false === $row ? null : PickupAuthorization::reconstitute(
            (string) $row['id'],
            (string) $row['programme_id'],
            (string) $row['child_id'],
            (string) $row['collector_id'],
            (string) $row['authority_id'],
            Timestamps::fromDatabase((string) $row['valid_from']),
            Timestamps::fromDatabase((string) $row['valid_until']),
            (string) $row['evidence_reference'],
            (string) $row['recorded_by'],
            self::time($row['revoked_at']),
            null === $row['revocation_reason'] ? null : (string) $row['revocation_reason'],
            (int) $row['version'],
        );
    }

    public function insertPickupAuthorization(PickupAuthorization $grant): void
    {
        $this->connection->insert(self::PICKUPS, ['id' => $grant->id] + $this->pickupColumns($grant));
    }

    public function updatePickupAuthorization(PickupAuthorization $grant): void
    {
        $changed = $this->connection->update(self::PICKUPS, $this->pickupColumns($grant), ['id' => $grant->id, 'version' => $grant->version - 1]);
        if (1 !== $changed) {
            throw ChildrenException::conflict('Record changed. Reload and try again.');
        }
    }

    public function guardianAuthority(string $id): ?GuardianAuthorityView
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from(self::AUTHORITIES)->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : $this->authorityView($row);
    }

    public function pickupAuthorization(string $id): ?PickupAuthorizationView
    {
        $row = $this->connection->createQueryBuilder()->select('*')->from(self::PICKUPS)->where('id = :id')->setParameter('id', $id)->fetchAssociative();

        return false === $row ? null : $this->pickupView($row);
    }

    public function authorities(string $childId): array
    {
        $rows = $this->connection->createQueryBuilder()->select('*')->from(self::AUTHORITIES)
            ->where('child_id = :child')->setParameter('child', $childId)
            ->orderBy('valid_from')->addOrderBy('id')->fetchAllAssociative();

        return array_values(array_map($this->authorityView(...), $rows));
    }

    public function authorizations(string $childId): array
    {
        $rows = $this->connection->createQueryBuilder()->select('*')->from(self::PICKUPS)
            ->where('child_id = :child')->setParameter('child', $childId)
            ->orderBy('valid_from')->addOrderBy('id')->fetchAllAssociative();

        return array_values(array_map($this->pickupView(...), $rows));
    }

    public function validAuthorizations(string $programmeId, string $childId, string $collectorId, DateTimeImmutable $at): array
    {
        $rows = $this->connection->createQueryBuilder()->select('*')->from(self::PICKUPS)
            ->where('programme_id = :programme')->andWhere('child_id = :child')->andWhere('collector_id = :collector')
            ->andWhere('revoked_at IS NULL')->andWhere('valid_from <= :at')->andWhere('valid_until >= :at')
            ->setParameter('programme', $programmeId)->setParameter('child', $childId)->setParameter('collector', $collectorId)
            ->setParameter('at', Timestamps::toDatabase($at))
            ->fetchAllAssociative();

        return array_values(array_map($this->pickupView(...), $rows));
    }

    public function validConsents(string $programmeId, string $policyId, string $childId, DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM children_consents WHERE programme_id = :programme AND policy_id = :policy AND child_id = :child AND revoked_at IS NULL AND valid_from <= :from AND valid_until >= :until ORDER BY accepted_at DESC, id',
            ['programme' => $programmeId, 'policy' => $policyId, 'child' => $childId, 'from' => Timestamps::toDatabase($from), 'until' => Timestamps::toDatabase($until)],
        );

        return array_map($this->consentView(...), $rows);
    }

    private function authorityRow(string $id, bool $lock): ?GuardianAuthority
    {
        $query = $this->connection->createQueryBuilder()->select('*')->from(self::AUTHORITIES)->where('id = :id')->setParameter('id', $id);
        if ($lock) {
            $query->forUpdate();
        }
        $row = $query->fetchAssociative();
        if (false === $row) {
            return null;
        }
        $view = $this->authorityView($row);

        return GuardianAuthority::reconstitute($view->id, $view->childId, $view->guardianId, $view->validFrom, $view->validUntil, $view->evidenceReference, $view->verifiedBy, $view->revokedAt, $view->revocationReason, $view->version);
    }

    /** @return array<string, mixed> */
    private function authorityColumns(GuardianAuthority $authority): array
    {
        return [
            'child_id' => $authority->childId,
            'guardian_id' => $authority->guardianId,
            'valid_from' => Timestamps::toDatabase($authority->validFrom),
            'valid_until' => Timestamps::toDatabase($authority->validUntil),
            'evidence_reference' => $authority->evidenceReference,
            'verified_by' => $authority->verifiedBy,
            'revoked_at' => null === $authority->revokedAt ? null : Timestamps::toDatabase($authority->revokedAt),
            'revocation_reason' => $authority->revocationReason,
            'version' => $authority->version,
        ];
    }

    /** @return array<string, mixed> */
    private function pickupColumns(PickupAuthorization $grant): array
    {
        return [
            'programme_id' => $grant->programmeId,
            'child_id' => $grant->childId,
            'collector_id' => $grant->collectorId,
            'authority_id' => $grant->authorityId,
            'valid_from' => Timestamps::toDatabase($grant->validFrom),
            'valid_until' => Timestamps::toDatabase($grant->validUntil),
            'evidence_reference' => $grant->evidenceReference,
            'recorded_by' => $grant->recordedBy,
            'revoked_at' => null === $grant->revokedAt ? null : Timestamps::toDatabase($grant->revokedAt),
            'revocation_reason' => $grant->revocationReason,
            'version' => $grant->version,
        ];
    }

    /** @param array<string, mixed> $row */
    private function authorityView(array $row): GuardianAuthorityView
    {
        return new GuardianAuthorityView(
            (string) $row['id'],
            (string) $row['child_id'],
            (string) $row['guardian_id'],
            Timestamps::fromDatabase((string) $row['valid_from']),
            Timestamps::fromDatabase((string) $row['valid_until']),
            (string) $row['evidence_reference'],
            (string) $row['verified_by'],
            self::time($row['revoked_at']),
            null === $row['revocation_reason'] ? null : (string) $row['revocation_reason'],
            (int) $row['version'],
        );
    }

    /** @param array<string, mixed> $row */
    private function pickupView(array $row): PickupAuthorizationView
    {
        return new PickupAuthorizationView(
            (string) $row['id'],
            (string) $row['programme_id'],
            (string) $row['child_id'],
            (string) $row['collector_id'],
            (string) $row['authority_id'],
            Timestamps::fromDatabase((string) $row['valid_from']),
            Timestamps::fromDatabase((string) $row['valid_until']),
            (string) $row['evidence_reference'],
            (string) $row['recorded_by'],
            self::time($row['revoked_at']),
            null === $row['revocation_reason'] ? null : (string) $row['revocation_reason'],
            (int) $row['version'],
        );
    }

    private static function time(mixed $value): ?DateTimeImmutable
    {
        return null === $value ? null : Timestamps::fromDatabase((string) $value);
    }
}
