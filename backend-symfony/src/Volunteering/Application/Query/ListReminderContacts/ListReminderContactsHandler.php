<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\ListReminderContacts;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonEmail;
use App\Volunteering\Application\ReminderReview;
use App\Volunteering\Application\ServingPermissions;

final readonly class ListReminderContactsHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private ReminderReview $review,
    ) {
    }

    /** @return list<PersonEmail> */
    public function handle(ListReminderContactsQuery $query): array
    {
        $this->auth->requirePermission(ServingPermissions::COMMUNICATIONS);

        return $this->review->contacts($query->assignmentId);
    }
}
