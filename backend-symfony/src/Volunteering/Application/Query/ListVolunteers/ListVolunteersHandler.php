<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\ListVolunteers;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;
use App\Volunteering\Application\Contract\VolunteerDirectory;
use App\Volunteering\Application\Contract\VolunteerPage;
use App\Volunteering\Application\ServingPermissions;
use App\Volunteering\Application\ServingViews;
use App\Volunteering\Application\VolunteerNames;
use App\Volunteering\Domain\VolunteerException;

final readonly class ListVolunteersHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private VolunteerDirectory $volunteers,
        private PersonDirectory $people,
        private VolunteerNames $names,
    ) {
    }

    public function handle(ListVolunteersQuery $query): VolunteerPage
    {
        $this->auth->requirePermission(ServingPermissions::READ);
        ServingViews::page($query->limit, $query->offset);
        if (null === $query->search || mb_strlen($query->search) > 150) {
            throw VolunteerException::invalid('Use at most 150 search characters.');
        }
        $people = '' === trim($query->search) ? null : $this->people->matchingIds($query->search);

        return new VolunteerPage(
            array_map($this->names->summary(...), $this->volunteers->volunteers($people, $query->limit, $query->offset)),
            $this->volunteers->volunteerCount($people),
        );
    }
}
