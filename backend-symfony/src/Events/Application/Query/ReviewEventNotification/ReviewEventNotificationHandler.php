<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ReviewEventNotification;

use App\Events\Application\Contract\ReviewedNotice;
use App\Events\Application\EventMessageReview;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use Psr\Clock\ClockInterface;

final readonly class ReviewEventNotificationHandler
{
    public function __construct(private PermissionChecker $authorization, private EventMessageReview $review, private ClockInterface $clock)
    {
    }

    public function handle(ReviewEventNotificationQuery $query): ReviewedNotice
    {
        $this->authorization->requirePermission('events.communications.send');

        return $this->review->review($query->registrationId, $query->contactId, $query->purpose, $query->message, $this->clock->now());
    }
}
