<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ListEventMessageContacts;

use App\Events\Application\EventMessageReview;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonEmail;

final readonly class ListEventMessageContactsHandler
{
    public function __construct(private PermissionChecker $authorization, private EventMessageReview $review)
    {
    }

    /** @return list<PersonEmail> */
    public function handle(ListEventMessageContactsQuery $query): array
    {
        $this->authorization->requirePermission('events.communications.send');

        return $this->review->contacts($query->registrationId);
    }
}
