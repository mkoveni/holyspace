<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Infrastructure\Persistence;

use App\ChildrenMinistry\Domain\Repository\ChildrenMinistryRepository;
use App\Shared\Infrastructure\Persistence\WorkflowAuditJournal;
use App\Shared\Infrastructure\Persistence\WorkflowDocumentRepository;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ChildrenMinistryRepository::class)]
final class DbalChildrenMinistryRepository extends WorkflowDocumentRepository implements ChildrenMinistryRepository
{
    public function __construct(Connection $db, ChildrenMinistryMapper $mapper, WorkflowAuditJournal $audit)
    {
        parent::__construct($db, $mapper, 'childrenministry_records', $audit);
    }
}
