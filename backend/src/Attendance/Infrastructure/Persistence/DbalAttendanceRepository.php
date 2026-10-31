<?php

declare(strict_types=1);

namespace App\Attendance\Infrastructure\Persistence;

use App\Attendance\Domain\Repository\AttendanceRepository;
use App\Shared\Infrastructure\Persistence\WorkflowAuditJournal;
use App\Shared\Infrastructure\Persistence\WorkflowDocumentRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(AttendanceRepository::class)]
final class DbalAttendanceRepository extends WorkflowDocumentRepository implements AttendanceRepository
{
    public function __construct(Connection $db, AttendanceMapper $mapper, WorkflowAuditJournal $audit)
    {
        parent::__construct($db, $mapper, 'attendance_records', $audit);
    }
}
