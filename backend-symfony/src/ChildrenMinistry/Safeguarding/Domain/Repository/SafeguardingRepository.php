<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Domain\Repository;

use App\ChildrenMinistry\Safeguarding\Domain\Consent;
use App\ChildrenMinistry\Safeguarding\Domain\ConsentPolicy;
use App\ChildrenMinistry\Safeguarding\Domain\GuardianAuthority;
use App\ChildrenMinistry\Safeguarding\Domain\PickupAuthorization;

/** Write side of guardian authority, consents, consent policies and pickup grants. */
interface SafeguardingRepository
{
    /** Reads without locking; callers that change the record use the ForUpdate variant. */
    public function findGuardianAuthority(string $id): ?GuardianAuthority;

    public function guardianAuthorityForUpdate(string $id): ?GuardianAuthority;

    public function insertGuardianAuthority(GuardianAuthority $authority): void;

    public function updateGuardianAuthority(GuardianAuthority $authority): void;

    public function pickupAuthorizationForUpdate(string $id): ?PickupAuthorization;

    public function insertPickupAuthorization(PickupAuthorization $grant): void;

    public function updatePickupAuthorization(PickupAuthorization $grant): void;

    public function findConsentPolicy(string $id): ?ConsentPolicy;

    public function insertConsentPolicy(ConsentPolicy $policy): void;

    public function consentForUpdate(string $id): ?Consent;

    public function insertConsent(Consent $consent): void;

    public function updateConsent(Consent $consent): void;
}
