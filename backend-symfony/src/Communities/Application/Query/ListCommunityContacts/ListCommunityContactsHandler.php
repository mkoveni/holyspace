<?php

declare(strict_types=1);

namespace App\Communities\Application\Query\ListCommunityContacts;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\NoticeReview;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonEmail;

/** The current email addresses of a current member, for leaders and managers who may send notices. */
final readonly class ListCommunityContactsHandler
{
    public function __construct(private PermissionChecker $auth, private CommunityAccess $access, private NoticeReview $review)
    {
    }

    /** @return list<PersonEmail> */
    public function handle(ListCommunityContactsQuery $query): array
    {
        $this->auth->requirePermission(CommunityAccess::SEND);
        $this->access->directoryScope();

        return $this->review->contacts($query->membershipId);
    }
}
