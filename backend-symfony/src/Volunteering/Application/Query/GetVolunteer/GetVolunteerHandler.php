<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\GetVolunteer;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Volunteering\Application\Contract\CredentialView;
use App\Volunteering\Application\Contract\ProfileCredential;
use App\Volunteering\Application\Contract\VolunteerDirectory;
use App\Volunteering\Application\Contract\VolunteerProfile;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Application\VolunteerNames;
use App\Volunteering\Domain\VolunteerException;

/**
 * Java `ServingViews.profile`: the profile, its credentials without evidence notes, and the current availability
 * revision.
 */
final readonly class GetVolunteerHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private VolunteerDirectory $volunteers,
        private VolunteerNames $names,
    ) {
    }

    public function handle(GetVolunteerQuery $query): VolunteerProfile
    {
        $this->auth->requirePermission(ServingPermissions::READ);
        $volunteer = $this->volunteers->volunteer($query->volunteerId) ?? throw VolunteerException::missing('Volunteer');
        $types = [];
        foreach ($this->volunteers->credentialTypes() as $type) {
            $types[$type->id] = $type->name;
        }
        $credentials = array_map(static fn (CredentialView $c): ProfileCredential => new ProfileCredential(
            $c->id,
            $c->typeId,
            $types[$c->typeId] ?? 'Credential',
            $c->window->validFrom,
            $c->window->validUntil,
            $c->window->revokedAt,
        ), $this->volunteers->credentials($volunteer->id));

        return new VolunteerProfile(
            $this->names->summary($volunteer),
            $credentials,
            $this->volunteers->weeklyRules($volunteer->id, $volunteer->availabilityRevision),
            $this->volunteers->exceptionWindows($volunteer->id, $volunteer->availabilityRevision),
        );
    }
}
