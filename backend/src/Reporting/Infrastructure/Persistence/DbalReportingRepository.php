<?php

declare(strict_types=1);

namespace App\Reporting\Infrastructure\Persistence;

use App\Reporting\Domain\Repository\ReportingRepository;
use App\Shared\Infrastructure\Persistence\WorkflowAuditJournal;
use App\Shared\Infrastructure\Persistence\WorkflowDocumentRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ReportingRepository::class)]
final class DbalReportingRepository extends WorkflowDocumentRepository implements ReportingRepository
{
    public function __construct(Connection $db, ReportingMapper $mapper, WorkflowAuditJournal $audit)
    {
        parent::__construct($db, $mapper, 'reporting_records', $audit);
    }
}
