<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Query\ReviewVolunteerReminder;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Volunteering\Application\Contract\ReminderPreview;
use App\Volunteering\Application\ReminderReview;
use App\Volunteering\Application\ServingPermissions;
use Psr\Clock\ClockInterface;

final readonly class ReviewVolunteerReminderHandler
{
    public function __construct(
        private PermissionChecker $auth,
        private ReminderReview $review,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ReviewVolunteerReminderQuery $query): ReminderPreview
    {
        $this->auth->requirePermission(ServingPermissions::COMMUNICATIONS);

        return $this->review->review($query->assignmentId, $query->contactId, $query->message, $this->clock->now());
    }
}
