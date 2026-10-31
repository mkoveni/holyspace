<?php

declare(strict_types=1);

namespace App\Reporting\Application\Command\CreateFollowupTask;

use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\People\Application\ReadModel\PersonDirectory;
use App\Reporting\Application\FollowupAccounts;
use App\Reporting\Application\Query\EvaluateReportCriteria\EvaluateReportCriteriaHandler;
use App\Reporting\Application\Query\EvaluateReportCriteria\EvaluateReportCriteriaQuery;
use App\Reporting\Application\Query\GetFollowupTask\GetFollowupTaskHandler;
use App\Reporting\Application\Query\GetFollowupTask\GetFollowupTaskQuery;
use App\Reporting\Application\Query\GetSegment\GetSegmentHandler;
use App\Reporting\Application\Query\GetSegment\GetSegmentQuery;
use App\Reporting\Domain\Model\FollowupTask;
use App\Reporting\Domain\Model\SegmentCriteria;
use App\Reporting\Domain\Repository\ReportingRepository;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CreateFollowupTaskHandler
{
    public function __construct(
        private ReportingRepository $records,
        private FollowupAccounts $accounts,
        private PersonDirectory $people,
        private WorkflowTransaction $transactions,
        private AuthenticatedAccount $tokens,
        private AccessControl $access,
        private GetSegmentHandler $getSegmentHandler,
        private EvaluateReportCriteriaHandler $evaluateReportCriteriaHandler,
        private GetFollowupTaskHandler $getFollowupTaskHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(CreateFollowupTaskCommand $message): array
    {
        $personId = $message->personId;
        $assigneeId = $message->assigneeId;
        $title = $message->title;
        $dueOn = $message->dueOn;
        $segmentId = $message->segmentId;
        $this->requirePermissions(['followups.manage', 'people.read']);
        $this->userId();

        return $this->transactions->run(function () use ($personId, $assigneeId, $title, $dueOn, $segmentId) {
            $required = ['people.read'];
            if (null !== $segmentId) {
                $s = ($this->getSegmentHandler)(new GetSegmentQuery($segmentId));
                $required = SegmentCriteria::permissions($s->filters);
                if (!in_array($personId, array_column(($this->evaluateReportCriteriaHandler)(new EvaluateReportCriteriaQuery($s->filters))['items'], 'id'), true)) {
                    throw new DomainException('Person no longer matches this segment.');
                }
            }
            if (!$this->people->get($personId)) {
                throw new OutOfBoundsException('Person not found.');
            }
            if (!$this->accounts->eligible($assigneeId, ['followups.work', ...$required])) {
                throw new DomainException('Choose an active assignee with follow-up and source permissions.');
            }
            $task = new FollowupTask($personId, $assigneeId, trim($title), $dueOn, $required);
            $this->records->save($task);

            return ($this->getFollowupTaskHandler)(new GetFollowupTaskQuery($task->id));
        });
    }

    private function requirePermissions(array $codes): void
    {
        foreach ($codes as $code) {
            if (!$this->access->isGranted($code)) {
                throw new AccessDenied('Source permissions are required for this report or task.');
            }
        }
    }

    private function userId(): string
    {
        $user = $this->tokens->user();
        if (null === $user || 'active' !== $user->status()->value) {
            throw new AccessDenied('An active staff account is required.');
        }

        return $user->id()->toString();
    }
}
