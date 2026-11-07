<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\CreateTemplate;

use App\Communications\Application\Contract\CommunicationAudit;
use App\Communications\Application\Contract\RichText;
use App\Communications\Domain\EmailTemplate;
use App\Communications\Domain\Repository\TemplateRepository;
use App\Communications\Domain\TemplateContent;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class CreateTemplateHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private TemplateRepository $repository,
        private RichText $richText,
        private CommunicationAudit $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
    ) {
    }

    public function handle(CreateTemplateCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'communications.templates.manage');
        $now = $this->clock->now();
        $content = new TemplateContent($command->subject, $this->richText->sanitize($command->html), $command->variables);
        $template = EmailTemplate::draft($this->ids->uuid(), $command->key, $command->name, $content, $command->actorId, $now);
        $this->repository->insert($template);
        $this->audit->record($command->actorId, 'template.createtemplate', $template->id, '', $now);

        return $template->id;
    }
}
