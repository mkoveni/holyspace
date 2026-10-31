<?php

declare(strict_types=1);

namespace App\Reporting\Application\Command\SaveFollowupRule;

use App\IdentityAccess\Application\Port\AuthenticatedAccount;
use App\Reporting\Application\FollowupAccounts;
use App\Reporting\Application\Query\GetSegment\GetSegmentHandler;
use App\Reporting\Application\Query\GetSegment\GetSegmentQuery;
use App\Reporting\Domain\Model\FollowupRule;
use App\Reporting\Domain\Model\SegmentCriteria;
use App\Reporting\Domain\Repository\ReportingRepository;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class SaveFollowupRuleHandler
{
    public function __construct(
        private ReportingRepository $records,
        private FollowupAccounts $accounts,
        private WorkflowTransaction $transactions,
        private AuthenticatedAccount $tokens,
        private AccessControl $access,
        private GetSegmentHandler $getSegmentHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(SaveFollowupRuleCommand $message): object
    {
        $data = $message->data;
        $id = $message->id;
        $version = $message->version;
        $this->requirePermissions(['reports.manage', 'followups.manage']);

        return $this->transactions->run(function () use ($data, $id, $version) {
            $s = ($this->getSegmentHandler)(new GetSegmentQuery($data['segmentId']));
            if ($s->version !== $data['segmentVersion']) {
                throw new DomainException('Review the latest segment before enabling this rule.');
            }
            if (!$this->accounts->eligible($data['assigneeId'], ['followups.work', ...SegmentCriteria::permissions($s->filters)])) {
                throw new DomainException('Assignee does not have required access.');
            }
            $old = null === $id ? null : $this->required('rules', $id);
            if ($old) {
                ($this->getSegmentHandler)(new GetSegmentQuery($old->segmentId));
                if ($old->version !== $version) {
                    throw new DomainException('Rule changed. Reload before saving.');
                }
            }
            $data['ownerId'] = $this->userId();
            $record = $old ? $old->revise($data) : new FollowupRule(...$data);
            $this->records->save($record);

            return $this->required('rules', $record->id);
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

    private function required(string $kind, string $id): mixed
    {
        return $this->records->find($kind, $id) ?? throw new OutOfBoundsException('Record not found.');
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
