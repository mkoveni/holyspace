<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Safeguarding\Application\ConsentPolicyView;
use App\ChildrenMinistry\Safeguarding\Application\ConsentView;
use App\ChildrenMinistry\Safeguarding\Application\Contract\SafeguardingReader;
use App\ChildrenMinistry\Safeguarding\Application\GuardianAuthorityView;
use App\ChildrenMinistry\Safeguarding\Application\PickupAuthorizationView;
use DateTimeImmutable;

/** Consents, authorities and pickup grants held in arrays; the time filters of the real reader are not repeated. */
final class FakeSafeguardingReader implements SafeguardingReader
{
    /** @var array<string, GuardianAuthorityView> */
    public array $authorities = [];
    /** @var list<ConsentView> */
    public array $consents = [];
    /** @var list<PickupAuthorizationView> */
    public array $grants = [];

    public function guardianAuthority(string $id): ?GuardianAuthorityView
    {
        return $this->authorities[$id] ?? null;
    }

    public function consent(string $id): ?ConsentView
    {
        return null;
    }

    public function consentPolicy(string $id): ?ConsentPolicyView
    {
        return null;
    }

    public function pickupAuthorization(string $id): ?PickupAuthorizationView
    {
        return null;
    }

    public function authorities(string $childId): array
    {
        return array_values(array_filter($this->authorities, static fn (GuardianAuthorityView $a): bool => $a->childId === $childId));
    }

    public function consents(string $childId): array
    {
        return $this->consents;
    }

    public function authorizations(string $childId): array
    {
        return array_values(array_filter($this->grants, static fn (PickupAuthorizationView $g): bool => $g->childId === $childId));
    }

    public function validAuthorizations(string $programmeId, string $childId, string $collectorId, DateTimeImmutable $at): array
    {
        return array_values(array_filter($this->grants, static fn (PickupAuthorizationView $g): bool => $g->programmeId === $programmeId && $g->childId === $childId && $g->collectorId === $collectorId && null === $g->revokedAt && $g->validFrom <= $at && $g->validUntil >= $at));
    }

    public function validConsents(string $programmeId, string $policyId, string $childId, DateTimeImmutable $from, DateTimeImmutable $until): array
    {
        return array_values(array_filter($this->consents, static fn (ConsentView $c): bool => $c->programmeId === $programmeId && $c->policyId === $policyId && $c->childId === $childId && null === $c->revokedAt && $c->validFrom <= $from && $c->validUntil >= $until));
    }
}
