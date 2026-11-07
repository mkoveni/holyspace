<?php

declare(strict_types=1);

namespace App\Communications\Application;

use App\Communications\Application\Contract\EmailTemplates;
use App\Communications\Application\Contract\RenderedEmail;
use App\Communications\Application\Contract\RichText;
use App\Communications\Application\Contract\TemplateReader;
use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\TemplateContent;

final readonly class PublishedEmailTemplates implements EmailTemplates
{
    public function __construct(private TemplateReader $reader, private RichText $richText)
    {
    }

    public function render(string $key, array $values): RenderedEmail
    {
        $revision = $this->reader->published($key)
            ?? throw CommunicationException::conflict('Publish an active template for '.$key.' before preparing this email.');
        $needed = [];
        foreach ($revision->variables as $name) {
            $needed[$name] = $values[$name] ?? throw CommunicationException::invalid('Missing template variable: '.$name);
        }
        $result = new TemplateContent($revision->subject, $revision->html, $revision->variables)->render($needed);

        return new RenderedEmail($key, $revision->version, $result->subject, $this->richText->sanitize($result->html));
    }
}
