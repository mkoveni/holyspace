<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\UpdateTemplate;

use App\Communications\Application\Contract\CommunicationAudit;
use App\Communications\Application\Contract\RichText;
use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\CommunicationRules;
use App\Communications\Domain\Repository\TemplateRepository;
use App\Communications\Domain\TemplateContent;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Saves a new draft. The published revision stays in use until the draft is published. */
final readonly class UpdateTemplateHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private TemplateRepository $repository,
        private RichText $richText,
        private CommunicationAudit $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(UpdateTemplateCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'communications.templates.manage');
        $current = $this->repository->lock($command->id) ?? throw CommunicationException::missing('Template');
        CommunicationRules::version($current->version, $command->expectedVersion);
        $now = $this->clock->now();
        $template = $current->edit($command->name, new TemplateContent($command->subject, $this->richText->sanitize($command->html), $command->variables), $command->actorId, $now);
        $this->repository->save($template);
        $this->audit->record($command->actorId, 'template.updatetemplate', $template->id, '', $now);

        return $template->id;
    }
}
