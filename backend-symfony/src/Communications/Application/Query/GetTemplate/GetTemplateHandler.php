<?php

declare(strict_types=1);

namespace App\Communications\Application\Query\GetTemplate;

use App\Communications\Application\Contract\TemplateReader;
use App\Communications\Application\Contract\TemplateView;
use App\Communications\Domain\CommunicationException;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetTemplateHandler
{
    public function __construct(private PermissionChecker $authorization, private TemplateReader $reader)
    {
    }

    public function handle(GetTemplateQuery $query): TemplateView
    {
        $this->authorization->requirePermission('communications.templates.read');

        return $this->reader->find($query->id) ?? throw CommunicationException::missing('Template');
    }
}
