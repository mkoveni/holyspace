<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\GetSegment;

use App\Reporting\Application\ReadModel\ReportingReadStore;
use App\Reporting\Domain\Model\SavedSegment;
use App\Reporting\Domain\Model\SegmentCriteria;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;
use OutOfBoundsException;

final readonly class GetSegmentHandler
{
    public function __construct(private ReportingReadStore $records, private AccessControl $access)
    {
    }

    public function __invoke(GetSegmentQuery $message): SavedSegment
    {
        $id = $message->id;
        $this->requirePermissions(['reports.read']);
        $s = $this->required('segments', $id);
        $this->requirePermissions(SegmentCriteria::permissions($s->filters));

        return $s;
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
