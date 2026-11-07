<?php

declare(strict_types=1);

namespace App\Communications\Application\Query\ListTemplateRevisions;

use App\Communications\Application\Contract\TemplateReader;
use App\Communications\Application\Contract\TemplateRevision;
use App\Communications\Domain\CommunicationException;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class ListTemplateRevisionsHandler
{
    public function __construct(private PermissionChecker $authorization, private TemplateReader $reader)
    {
    }

    /** @return list<TemplateRevision> */
    public function handle(ListTemplateRevisionsQuery $query): array
    {
        $this->authorization->requirePermission('communications.templates.read');
        if ($query->limit < 1 || $query->limit > 100 || $query->offset < 0 || $query->offset > 100000) {
            throw CommunicationException::invalid('Choose a bounded revision page.');
        }
        $this->reader->find($query->id) ?? throw CommunicationException::missing('Template');

        return $this->reader->revisions($query->id, $query->limit, $query->offset);
    }
}
