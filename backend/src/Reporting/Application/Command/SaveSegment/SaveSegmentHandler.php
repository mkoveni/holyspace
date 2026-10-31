<?php

declare(strict_types=1);

namespace App\Reporting\Application\Command\SaveSegment;

use App\Reporting\Application\Query\GetSegment\GetSegmentHandler;
use App\Reporting\Application\Query\GetSegment\GetSegmentQuery;
use App\Reporting\Domain\Model\SavedSegment;
use App\Reporting\Domain\Model\SegmentCriteria;
use App\Reporting\Domain\Repository\ReportingRepository;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use App\Shared\Application\WorkflowTransaction;
use DomainException;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class SaveSegmentHandler
{
    public function __construct(
        private ReportingRepository $records,
        private WorkflowTransaction $transactions,
        private AccessControl $access,
        private GetSegmentHandler $getSegmentHandler,
    ) {
    }

    #[AsMessageHandler]
    public function __invoke(SaveSegmentCommand $message): object
    {
        $name = $message->name;
        $filters = $message->filters;
        $id = $message->id;
        $version = $message->version;
        $this->requirePermissions(['reports.manage', ...SegmentCriteria::permissions($filters)]);

        return $this->transactions->run(function () use ($name, $filters, $id, $version) {
            $old = null === $id ? null : ($this->getSegmentHandler)(new GetSegmentQuery($id));
            if ($old && $old->version !== $version) {
                throw new DomainException('Segment changed. Reload before saving.');
            }
            $record = $old ? $old->revise($name, $filters) : new SavedSegment(trim($name), $filters);
            $this->records->save($record);

            return $this->required('segments', $record->id);
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
}
