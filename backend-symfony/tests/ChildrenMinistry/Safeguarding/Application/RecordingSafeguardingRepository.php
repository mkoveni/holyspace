<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Safeguarding\Application;

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
use App\Tests\ChildrenMinistry\Shared\CallLog;
use DateTimeImmutable;

/** In-memory repository and reader that log every call, so lock ordering can be asserted. */
final class RecordingSafeguardingRepository implements SafeguardingRepository, SafeguardingReader
{
    /** @var array<string, GuardianAuthority> */
    public array $authorities = [];
    /** @var array<string, PickupAuthorization> */
    public array $grants = [];

    /** @var array<string, ConsentPolicy> */
    public array $policies = [];
    /** @var array<string, Consent> */
    public array $consents = [];

    public function __construct(private readonly CallLog $log)
    {
    }

    public function findGuardianAuthority(string $id): ?GuardianAuthority
    {
        $this->log->entries[] = 'find:authority';

        return $this->authorities[$id] ?? null;
    }

    public function guardianAuthorityForUpdate(string $id): ?GuardianAuthority
    {
        $this->log->entries[] = 'forUpdate:authority';

        return $this->authorities[$id] ?? null;
    }

    public function insertGuardianAuthority(GuardianAuthority $authority): void
    {
        $this->log->entries[] = 'insert:authority';
        $this->authorities[$authority->id] = $authority;
    }

    public function updateGuardianAuthority(GuardianAuthority $authority): void
    {
        $this->log->entries[] = 'update:authority';
        $this->authorities[$authority->id] = $authority;
    }

    public function pickupAuthorizationForUpdate(string $id): ?PickupAuthorization
    {
        $this->log->entries[] = 'forUpdate:grant';

        return $this->grants[$id] ?? null;
    }

    public function insertPickupAuthorization(PickupAuthorization $grant): void
    {
        $this->log->entries[] = 'insert:grant';
        $this->grants[$grant->id] = $grant;
    }

    public function updatePickupAuthorization(PickupAuthorization $grant): void
    {
        $this->log->entries[] = 'update:grant';
        $this->grants[$grant->id] = $grant;
    }

    public function guardianAuthority(string $id): ?GuardianAuthorityView
    {
        $this->log->entries[] = 'read:authority';
        $a = $this->authorities[$id] ?? null;

        return null === $a ? null : new GuardianAuthorityView($a->id, $a->childId, $a->guardianId, $a->validFrom, $a->validUntil, $a->evidenceReference, $a->verifiedBy, $a->revokedAt, $a->revocationReason, $a->version);
    }

    public function pickupAuthorization(string $id): ?PickupAuthorizationView
    {
        $this->log->entries[] = 'read:grant';
        $g = $this->grants[$id] ?? null;

        return null === $g ? null : new PickupAuthorizationView($g->id, $g->programmeId, $g->childId, $g->collectorId, $g->authorityId, $g->validFrom, $g->validUntil, $g->evidenceReference, $g->recordedBy, $g->revokedAt, $g->revocationReason, $g->version);
    }

    public function authorities(string $childId): array
    {
        return [];
    }

    public function authorizations(string $childId): array
    {
        return [];
    }

    public function validAuthorizations(string $programmeId, string $childId, string $collectorId, DateTimeImmutable $at): array
    {
        return [];
    }

    public function validConsents(string $programmeId, string $policyId, string $childId, DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        return [];
    }

    public function findConsentPolicy(string $id): ?ConsentPolicy
    {
        $this->log->entries[] = 'find:policy';

        return $this->policies[$id] ?? null;
    }

    public function insertConsentPolicy(ConsentPolicy $policy): void
    {
        $this->log->entries[] = 'insert:policy';
        $this->policies[$policy->id] = $policy;
    }

    public function consentForUpdate(string $id): ?Consent
    {
        $this->log->entries[] = 'forUpdate:consent';

        return $this->consents[$id] ?? null;
    }

    public function insertConsent(Consent $consent): void
    {
        $this->log->entries[] = 'insert:consent';
        $this->consents[$consent->id] = $consent;
    }

    public function updateConsent(Consent $consent): void
    {
        $this->log->entries[] = 'update:consent';
        $this->consents[$consent->id] = $consent;
    }

    public function consent(string $id): ?ConsentView
    {
        $this->log->entries[] = 'read:consent';
        $c = $this->consents[$id] ?? null;

        return null === $c ? null : new ConsentView($c->id, $c->programmeId, $c->policyId, $c->childId, $c->authorityId, $c->validFrom, $c->validUntil, $c->acceptedAt, $c->recordedBy, $c->evidenceReference, $c->revokedAt, $c->revocationReason, $c->version);
    }

    public function consentPolicy(string $id): ?ConsentPolicyView
    {
        return null;
    }

    public function consents(string $childId): array
    {
        return [];
    }
}
