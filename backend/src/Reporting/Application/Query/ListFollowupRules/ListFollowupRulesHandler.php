<?php

declare(strict_types=1);

namespace App\Reporting\Application\Query\ListFollowupRules;

use App\Reporting\Application\ReadModel\ReportingReadStore;
use App\Reporting\Domain\Model\SegmentCriteria;
use App\Shared\Application\Exception\AccessDenied;
use App\Shared\Application\Security\AccessControl;

final readonly class ListFollowupRulesHandler
{
    public function __construct(private ReportingReadStore $records, private AccessControl $access)
    {
    }

    public function __invoke(ListFollowupRulesQuery $message): array
    {
        $this->requirePermissions(['reports.read', 'followups.manage']);
        $items = [];
        $segments = array_column($this->records->all('segments'), null, 'id');
        foreach ($this->records->all('rules') as $r) {
            $s = $segments[$r->segmentId] ?? null;
            if ($s && $this->allowed(SegmentCriteria::permissions($s->filters))) {
                $items[] = $r;
            }
        }

        return ['items' => $items];
    }

    private function requirePermissions(array $codes): void
    {
        foreach ($codes as $code) {
            if (!$this->access->isGranted($code)) {
                throw new AccessDenied('Source permissions are required for this report or task.');
            }
        }
    }

    private function allowed(array $codes): bool
    {
        foreach ($codes as $code) {
            if (!$this->access->isGranted($code)) {
                return false;
            }
        }

        return true;
    }
}
