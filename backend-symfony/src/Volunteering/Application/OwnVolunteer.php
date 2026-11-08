<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Volunteering\Application\Contract\VolunteerDirectory;
use App\Volunteering\Application\Contract\VolunteerView;
use App\Volunteering\Domain\Volunteer;
use App\Volunteering\Domain\VolunteerException;

/**
 * Java `MemberVolunteerActions.own/recheck`: the caller's linked profile, found by account with live
 * volunteering.serve, and rechecked once locked (still linked to the caller, still active).
 */
final readonly class OwnVolunteer
{
    public function __construct(
        private AuthenticatedAccount $identity,
        private PermissionChecker $auth,
        private VolunteerDirectory $volunteers,
        private VolunteerLocks $locks,
    ) {
    }

    /** @return array{string, VolunteerView} the caller's account id and linked profile */
    public function profile(): array
    {
        $accountId = $this->identity->requireAccount();
        $this->auth->requireActorPermission($accountId, ServingPermissions::SERVE);
        $volunteer = $this->volunteers->byAccount($accountId) ?? throw VolunteerException::missing('Linked volunteer profile');

        return [$accountId, $volunteer];
    }

    public function lock(string $accountId, string $volunteerId): Volunteer
    {
        $locked = $this->locks->lock($volunteerId);
        $this->auth->requireActorPermission($accountId, ServingPermissions::SERVE);
        if ($this->identity->requireAccount() !== $accountId || $locked->accountId !== $accountId || !$locked->isActive()) {
            throw VolunteerException::forbidden();
        }

        return $locked;
    }
}
