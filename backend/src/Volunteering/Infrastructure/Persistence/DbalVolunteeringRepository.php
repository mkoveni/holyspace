<?php

declare(strict_types=1);

namespace App\Volunteering\Infrastructure\Persistence;

use App\Shared\Infrastructure\Persistence\WorkflowAuditJournal;
use App\Shared\Infrastructure\Persistence\WorkflowDocumentRepository;
use App\Volunteering\Domain\Repository\VolunteeringRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(VolunteeringRepository::class)]
final class DbalVolunteeringRepository extends WorkflowDocumentRepository implements VolunteeringRepository
{
    public function __construct(Connection $db, VolunteeringMapper $mapper, WorkflowAuditJournal $audit)
    {
        parent::__construct($db, $mapper, 'volunteering_records', $audit);
    }
}
