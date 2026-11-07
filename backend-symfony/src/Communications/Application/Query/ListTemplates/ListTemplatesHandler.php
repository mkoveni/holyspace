<?php

declare(strict_types=1);

namespace App\Communications\Application\Query\ListTemplates;

use App\Communications\Application\Contract\TemplatePage;
use App\Communications\Application\Contract\TemplateReader;
use App\Communications\Domain\CommunicationException;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class ListTemplatesHandler
{
    public function __construct(private PermissionChecker $authorization, private TemplateReader $reader)
    {
    }

    public function handle(ListTemplatesQuery $query): TemplatePage
    {
        $this->authorization->requirePermission('communications.templates.read');
        if ($query->limit < 1 || $query->limit > 100 || $query->offset < 0 || $query->offset > 100000 || mb_strlen($query->search) > 150) {
            throw CommunicationException::invalid('Choose a bounded template page.');
        }

        return $this->reader->list($query->search, $query->limit, $query->offset);
    }
}
