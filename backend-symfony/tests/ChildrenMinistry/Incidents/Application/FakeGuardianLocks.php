<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Incidents\Application;

use App\ChildrenMinistry\Safeguarding\Domain\Consent;
use App\ChildrenMinistry\Safeguarding\Domain\ConsentPolicy;
use App\ChildrenMinistry\Safeguarding\Domain\GuardianAuthority;
use App\ChildrenMinistry\Safeguarding\Domain\PickupAuthorization;
use App\ChildrenMinistry\Safeguarding\Domain\Repository\SafeguardingRepository;
use App\Tests\ChildrenMinistry\Shared\CallLog;
use LogicException;

/** Only the authority lock is used by incidents; any other call is a mistake. */
final readonly class FakeGuardianLocks implements SafeguardingRepository
{
    /** @param array<string, GuardianAuthority> $authorities */
    public function __construct(private CallLog $log, private array $authorities)
    {
    }

    public function findGuardianAuthority(string $id): ?GuardianAuthority
    {
        throw new LogicException('Unexpected read.');
    }

    public function guardianAuthorityForUpdate(string $id): ?GuardianAuthority
    {
        $this->log->entries[] = 'lock:authority';

        return $this->authorities[$id] ?? null;
    }

    public function insertGuardianAuthority(GuardianAuthority $authority): void
    {
        throw new LogicException('Unexpected write.');
    }

    public function updateGuardianAuthority(GuardianAuthority $authority): void
    {
        throw new LogicException('Unexpected write.');
    }

    public function pickupAuthorizationForUpdate(string $id): ?PickupAuthorization
    {
        throw new LogicException('Unexpected lock.');
    }

    public function insertPickupAuthorization(PickupAuthorization $grant): void
    {
        throw new LogicException('Unexpected write.');
    }

    public function updatePickupAuthorization(PickupAuthorization $grant): void
    {
        throw new LogicException('Unexpected write.');
    }

    public function findConsentPolicy(string $id): ?ConsentPolicy
    {
        throw new LogicException('Unexpected read.');
    }

    public function insertConsentPolicy(ConsentPolicy $policy): void
    {
        throw new LogicException('Unexpected write.');
    }

    public function consentForUpdate(string $id): ?Consent
    {
        throw new LogicException('Unexpected lock.');
    }

    public function insertConsent(Consent $consent): void
    {
        throw new LogicException('Unexpected write.');
    }

    public function updateConsent(Consent $consent): void
    {
        throw new LogicException('Unexpected write.');
    }
}
