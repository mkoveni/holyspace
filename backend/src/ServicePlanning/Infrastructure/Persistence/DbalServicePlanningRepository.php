<?php

declare(strict_types=1);

namespace App\ServicePlanning\Infrastructure\Persistence;

use App\ServicePlanning\Domain\Repository\ServicePlanningRepository;
use App\Shared\Infrastructure\Persistence\WorkflowAuditJournal;
use App\Shared\Infrastructure\Persistence\WorkflowDocumentRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ServicePlanningRepository::class)]
final class DbalServicePlanningRepository extends WorkflowDocumentRepository implements ServicePlanningRepository
{
    public function __construct(Connection $db, ServicePlanningMapper $mapper, WorkflowAuditJournal $audit)
    {
        parent::__construct($db, $mapper, 'serviceplanning_records', $audit);
    }
}
