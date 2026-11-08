<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ReviewCommunityNotice;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\ReviewedNotice;
use App\Communities\Application\NoticeReview;
use App\IdentityAccess\Application\Contract\PermissionChecker;

/** Renders a member notice for review; nothing is stored or sent. */
final readonly class ReviewCommunityNoticeHandler
{
    public function __construct(private PermissionChecker $auth, private CommunityAccess $access, private NoticeReview $review)
    {
    }

    public function handle(ReviewCommunityNoticeQuery $query): ReviewedNotice
    {
        $this->auth->requirePermission(CommunityAccess::SEND);
        $this->access->directoryScope();

        return $this->review->review($query->membershipId, $query->contactId, $query->message);
    }
}
