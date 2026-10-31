<?php

declare(strict_types=1);

namespace App\Ministries\Infrastructure\Persistence;

use App\Ministries\Domain\Repository\MinistriesRepository;
use App\Shared\Infrastructure\Persistence\WorkflowAuditJournal;
use App\Shared\Infrastructure\Persistence\WorkflowDocumentRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(MinistriesRepository::class)]
final class DbalMinistriesRepository extends WorkflowDocumentRepository implements MinistriesRepository
{
    public function __construct(Connection $db, MinistriesMapper $mapper, WorkflowAuditJournal $audit)
    {
        parent::__construct($db, $mapper, 'ministries_records', $audit);
    }
}
