<?php

declare(strict_types=1);

namespace App\Communications\Infrastructure\Persistence;

use App\Communications\Domain\Repository\CommunicationsRepository;
use App\Shared\Infrastructure\Persistence\WorkflowAuditJournal;
use App\Shared\Infrastructure\Persistence\WorkflowDocumentRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(CommunicationsRepository::class)]
final class DbalCommunicationsRepository extends WorkflowDocumentRepository implements CommunicationsRepository
{
    public function __construct(Connection $db, CommunicationsMapper $mapper, WorkflowAuditJournal $audit)
    {
        parent::__construct($db, $mapper, 'communications_records', $audit);
    }
}
