<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\ArchiveTemplate;

use App\Communications\Application\Contract\CommunicationAudit;
use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\CommunicationRules;
use App\Communications\Domain\Repository\TemplateRepository;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Stops new rendering from the template; messages already approved keep their exact content. */
final readonly class ArchiveTemplateHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private TemplateRepository $repository,
        private CommunicationAudit $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ArchiveTemplateCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'communications.templates.manage');
        $current = $this->repository->lock($command->id) ?? throw CommunicationException::missing('Template');
        CommunicationRules::version($current->version, $command->expectedVersion);
        $now = $this->clock->now();
        $template = $current->archive($command->actorId, $now);
        $this->repository->save($template);
        $this->audit->record($command->actorId, 'template.archivetemplate', $template->id, '', $now);

        return $template->id;
    }
}
