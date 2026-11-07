<?php

declare(strict_types=1);

namespace App\Events\Application\Query\PreviewEventSchedule;

use App\Events\Application\SchedulePreview;
use App\Events\Application\ScheduleReview;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class PreviewEventScheduleHandler
{
    public function __construct(private PermissionChecker $authorization, private ScheduleReview $review)
    {
    }

    public function handle(PreviewEventScheduleQuery $query): SchedulePreview
    {
        $this->authorization->requirePermission('events.read');

        return $this->review->preview($query->draft);
    }
}
