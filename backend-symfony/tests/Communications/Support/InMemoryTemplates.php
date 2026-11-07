<?php

declare(strict_types=1);

namespace App\Tests\Communications\Support;

use App\Communications\Application\Contract\CommunicationAudit;
use App\Communications\Domain\EmailTemplate;
use App\Communications\Domain\Repository\TemplateRepository;
use DateTimeImmutable;

/** One template held in memory, with every write and audit entry logged in order. */
final class InMemoryTemplates implements TemplateRepository, CommunicationAudit
{
    /** @var list<string> */
    public array $log = [];

    public function __construct(public ?EmailTemplate $template = null)
    {
    }

    public function lock(string $id): ?EmailTemplate
    {
        return $this->template;
    }

    public function insert(EmailTemplate $template): void
    {
        $this->log[] = 'insert';
        $this->template = $template;
    }

    public function save(EmailTemplate $template): void
    {
        $this->log[] = 'save';
        $this->template = $template;
    }

    public function publish(EmailTemplate $template): void
    {
        $this->log[] = 'publish';
    }

    public function record(string $actor, string $action, string $target, string $detail, DateTimeImmutable $at): void
    {
        $this->log[] = 'audit:'.$action;
    }
}
