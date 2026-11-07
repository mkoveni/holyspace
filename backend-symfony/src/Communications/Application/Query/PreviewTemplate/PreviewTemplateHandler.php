<?php

declare(strict_types=1);

namespace App\Communications\Application\Query\PreviewTemplate;

use App\Communications\Application\Contract\RichText;
use App\Communications\Domain\RenderedContent;
use App\Communications\Domain\TemplateContent;
use App\IdentityAccess\Application\Contract\PermissionChecker;

/** Renders unsaved content so editors see exactly what recipients would; nothing is stored or sent. */
final readonly class PreviewTemplateHandler
{
    public function __construct(private PermissionChecker $authorization, private RichText $richText)
    {
    }

    public function handle(PreviewTemplateQuery $query): RenderedContent
    {
        $this->authorization->requirePermission('communications.templates.read');
        $content = new TemplateContent($query->subject, $this->richText->sanitize($query->html), $query->variables);
        $result = $content->render($query->values);

        return new RenderedContent($result->subject, $this->richText->sanitize($result->html));
    }
}
