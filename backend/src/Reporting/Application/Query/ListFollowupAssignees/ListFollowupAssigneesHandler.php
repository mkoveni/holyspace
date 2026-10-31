<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\ListFollowupAssignees;

use App\Reporting\Application\FollowupAccounts;
use App\Reporting\Application\Query\GetFollowupTask\GetFollowupTaskHandler;
use App\Reporting\Application\Query\GetFollowupTask\GetFollowupTaskQuery;
use App\Reporting\Application\Query\GetSegment\GetSegmentHandler;
use App\Reporting\Application\Query\GetSegment\GetSegmentQuery;
use App\Reporting\Domain\Model\SegmentCriteria;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use InvalidArgumentException;

final readonly class ListFollowupAssigneesHandler
{
    public function __construct(
        private FollowupAccounts $accounts,
        private AccessControl $access,
        private GetFollowupTaskHandler $getFollowupTaskHandler,
        private GetSegmentHandler $getSegmentHandler,
    ) {
    }

    public function __invoke(ListFollowupAssigneesQuery $message): array
    {
        $segmentId = $message->segmentId;
        $taskId = $message->taskId;
        $this->requirePermissions(['followups.manage', 'people.read']);
        if (null !== $segmentId && null !== $taskId) {
            throw new InvalidArgumentException('Choose a segment or task scope.');
        }
        $required = null !== $taskId ? ($this->getFollowupTaskHandler)(new GetFollowupTaskQuery($taskId))['requiredPermissions'] : (null === $segmentId ? ['people.read'] : SegmentCriteria::permissions(($this->getSegmentHandler)(new GetSegmentQuery($segmentId))->filters));

        return ['items' => $this->accounts->assignees($required)];
    }

    private function requirePermissions(array $codes): void
    {
        foreach ($codes as $code) {
            if (!$this->access->isGranted($code)) {
                throw new AccessDenied('Source permissions are required for this report or task.');
            }
        }
    }
}
