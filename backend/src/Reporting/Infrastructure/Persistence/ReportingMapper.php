<?php

declare(strict_types=1);

namespace App\Reporting\Infrastructure\Persistence;

use App\Reporting\Domain\Model\FollowupRule;
use App\Reporting\Domain\Model\FollowupTask;
use App\Reporting\Domain\Model\SavedSegment;
use App\Shared\Infrastructure\Persistence\WorkflowDocumentMapper;

final class ReportingMapper extends WorkflowDocumentMapper
{
    protected function types(): array
    {
        return ['segments' => SavedSegment::class, 'tasks' => FollowupTask::class, 'rules' => FollowupRule::class];
    }
}
