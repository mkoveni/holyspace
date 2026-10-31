<?php

declare(strict_types=1);

namespace App\Attendance\Infrastructure\Persistence;

use App\Attendance\Domain\Model\AttendanceEntry;
use App\Attendance\Domain\Model\VisitorCount;
use App\Shared\Infrastructure\Persistence\WorkflowDocumentMapper;

final class AttendanceMapper extends WorkflowDocumentMapper
{
    protected function types(): array
    {
        return ['entries' => AttendanceEntry::class, 'visitors' => VisitorCount::class];
    }
}
