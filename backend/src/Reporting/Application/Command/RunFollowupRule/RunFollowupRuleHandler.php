<?php

declare(strict_types=1);

namespace App\Reporting\Application\Command\RunFollowupRule;

use App\Reporting\Application\FollowupAccounts;
use App\Reporting\Application\Query\EvaluateReportCriteria\EvaluateReportCriteriaHandler;
use App\Reporting\Application\Query\EvaluateReportCriteria\EvaluateReportCriteriaQuery;
use App\Reporting\Domain\Model\FollowupTask;
use App\Reporting\Domain\Model\SegmentCriteria;
use App\Reporting\Domain\Repository\ReportingRepository;
use App\Shared\Application\WorkflowTransaction;
use DateTimeZone;
use InvalidArgumentException;
use OutOfBoundsException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class RunFollowupRuleHandler
{
    public function __construct(
        private ReportingRepository $records,
        private FollowupAccounts $accounts,
        private WorkflowTransaction $transactions,
        private ClockInterface $clock,
        private EvaluateReportCriteriaHandler $evaluateReportCriteriaHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(RunFollowupRuleCommand $message): array
    {
        $id = $message->id;
        $force = $message->force;

        return $this->transactions->run(function () use ($id, $force) {
            $r = $this->records->find('rules', $id) ?? throw new OutOfBoundsException('Rule not found.');
            if (!$r->active) {
                return ['created' => 0, 'paused' => false, 'message' => 'Rule is disabled.'];
            }
            $s = $this->records->find('segments', $r->segmentId);
            $reason = null;
            if (!$s || $s->version !== $r->segmentVersion) {
                $reason = 'Segment changed; review and reactivate the rule.';
            } elseif (!$this->accounts->eligible($r->ownerId, ['reports.manage', 'followups.manage', ...SegmentCriteria::permissions($s->filters)])) {
                $reason = 'Owner lost required access; an authorised manager must review the rule.';
            } elseif (!$this->accounts->eligible($r->assigneeId, ['followups.work', ...SegmentCriteria::permissions($s->filters)])) {
                $reason = 'Assignee lost required access; review assignment before reactivation.';
            }
            if (null !== $reason) {
                $this->records->save($r->pause($reason));

                return ['created' => 0, 'paused' => true, 'message' => $reason];
            }
            $today = $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d');
            if (!$force && null !== $r->lastRunAt && substr($r->lastRunAt, 0, 10) === $today) {
                return ['created' => 0, 'paused' => false, 'message' => 'Already evaluated today.'];
            }
            try {
                $matches = ($this->evaluateReportCriteriaHandler)(new EvaluateReportCriteriaQuery($s->filters))['items'];
            } catch (InvalidArgumentException|OutOfBoundsException $error) {
                $this->records->save($r->pause('Segment cannot be evaluated; review its filters.'));

                return ['created' => 0, 'paused' => true];
            }
            if (count($matches) > 1000) {
                $this->records->save($r->pause('More than 1000 matches; narrow the segment before reactivation.'));

                return ['created' => 0, 'paused' => true];
            }
            $existing = [];
            foreach ($this->records->all('tasks') as $task) {
                if ($task->ruleId === $id) {
                    $existing[$task->personId] = true;
                }
            }
            $created = 0;
            foreach ($matches as $person) {
                if (!isset($existing[$person['id']])) {
                    $due = $this->clock->now()->setTimezone(new DateTimeZone('UTC'))->modify('+'.$r->dueInDays.' days')->format('Y-m-d');
                    $this->records->save(new FollowupTask($person['id'], $r->assigneeId, $r->taskTitle, $due, SegmentCriteria::permissions($s->filters), $id));
                    ++$created;
                }
            }
            $this->records->save($r->ran($this->clock->now()->setTimezone(new DateTimeZone('UTC'))->format(DATE_ATOM)));

            return ['created' => $created, 'paused' => false, 'message' => 'One task per person and rule; no messages sent.'];
        });
    }
}
