<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Contract;

use App\ChildrenMinistry\Messaging\Application\CommunicationPage;
use App\ChildrenMinistry\Messaging\Application\CommunicationReceipt;
use App\ChildrenMinistry\Messaging\Application\CommunicationView;
use App\Shared\Domain\CalendarDate;

/** Read side of guardian communications. */
interface GuardianCommunicationReader
{
    public function receipt(string $actorId, string $requestId): ?CommunicationReceipt;

    public function find(string $id): ?CommunicationView;

    /**
     * By due date. The overdue total counts drafts due today or earlier within the child and status filters.
     *
     * @param string $status ALL, DRAFT, CONTACTED or CANCELLED
     */
    public function list(?string $childId, string $status, CalendarDate $on, int $limit, int $offset): CommunicationPage;
}
