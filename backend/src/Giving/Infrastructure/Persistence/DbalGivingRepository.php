<?php

declare(strict_types=1);

namespace App\Giving\Infrastructure\Persistence;

use App\Giving\Domain\Repository\GivingRepository;
use App\Shared\Infrastructure\Persistence\WorkflowAuditJournal;
use App\Shared\Infrastructure\Persistence\WorkflowDocumentRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(GivingRepository::class)]
final class DbalGivingRepository extends WorkflowDocumentRepository implements GivingRepository
{
    public function __construct(Connection $db, GivingMapper $mapper, WorkflowAuditJournal $audit)
    {
        parent::__construct($db, $mapper, 'giving_records', $audit);
    }
}
