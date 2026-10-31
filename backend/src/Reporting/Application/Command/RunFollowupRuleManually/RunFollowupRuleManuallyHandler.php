<?php

declare(strict_types=1);

namespace App\Reporting\Application\Command\RunFollowupRuleManually;

use App\Reporting\Application\Command\RunFollowupRule\RunFollowupRuleCommand;
use App\Reporting\Application\Command\RunFollowupRule\RunFollowupRuleHandler;
use App\Reporting\Application\Query\GetSegment\GetSegmentHandler;
use App\Reporting\Application\Query\GetSegment\GetSegmentQuery;
use App\Reporting\Domain\Repository\ReportingRepository;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class RunFollowupRuleManuallyHandler
{
    public function __construct(
        private ReportingRepository $records,
        private AccessControl $access,
        private GetSegmentHandler $getSegmentHandler,
        private RunFollowupRuleHandler $runFollowupRuleHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(RunFollowupRuleManuallyCommand $message): array
    {
        $id = $message->id;
        $this->requirePermissions(['reports.manage', 'followups.manage']);
        $r = $this->required('rules', $id);
        ($this->getSegmentHandler)(new GetSegmentQuery($r->segmentId));

        return ($this->runFollowupRuleHandler)(new RunFollowupRuleCommand($id, true));
    }

    private function requirePermissions(array $codes): void
    {
        foreach ($codes as $code) {
            if (!$this->access->isGranted($code)) {
                throw new AccessDenied('Source permissions are required for this report or task.');
            }
        }
    }

    private function required(string $kind, string $id): mixed
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
    }
}
