<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Contract;

use App\ChildrenMinistry\Safeguarding\Application\ConsentPolicyView;
use App\ChildrenMinistry\Safeguarding\Application\ConsentView;
use App\ChildrenMinistry\Safeguarding\Application\GuardianAuthorityView;
use App\ChildrenMinistry\Safeguarding\Application\PickupAuthorizationView;
use DateTimeImmutable;

/** Read side of guardian authority, consents, consent policies and pickup grants. */
interface SafeguardingReader
{
    public function guardianAuthority(string $id): ?GuardianAuthorityView;

    public function consent(string $id): ?ConsentView;

    public function consentPolicy(string $id): ?ConsentPolicyView;

    public function pickupAuthorization(string $id): ?PickupAuthorizationView;

    /** @return list<GuardianAuthorityView> ordered by start, then id */
    public function authorities(string $childId): array;

    /** @return list<ConsentView> ordered by acceptance, then id */
    public function consents(string $childId): array;

    /** @return list<PickupAuthorizationView> ordered by start, then id */
    public function authorizations(string $childId): array;

    /**
     * Unrevoked grants for this collector that span the instant.
     *
     * @return list<PickupAuthorizationView>
     */
    public function validAuthorizations(string $programmeId, string $childId, string $collectorId, DateTimeImmutable $at): array;

    /**
     * Unrevoked consents for the programme policy that span [from, until], newest acceptance first.
     *
     * @return list<ConsentView>
     */
    public function validConsents(string $programmeId, string $policyId, string $childId, DateTimeImmutable $from, DateTimeImmutable $until): array;
}
