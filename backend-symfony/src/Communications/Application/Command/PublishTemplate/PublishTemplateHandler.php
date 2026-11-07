<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\PublishTemplate;

use App\Communications\Application\Contract\CommunicationAudit;
use App\Communications\Application\Contract\TemplatePublicationPolicy;
use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\CommunicationRules;
use App\Communications\Domain\Repository\TemplateRepository;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Freezes the draft as a new immutable revision, after every source context agrees it is fit for its use. */
final readonly class PublishTemplateHandler implements CommandHandler
{
    /** @var list<TemplatePublicationPolicy> */
    private array $policies;

    /** @param iterable<TemplatePublicationPolicy> $policies */
    public function __construct(
        private PermissionChecker $authorization,
        private TemplateRepository $repository,
        private CommunicationAudit $audit,
        private ClockInterface $clock,
        iterable $policies,
    ) {
        $this->policies = array_values([...$policies]);
    }

    public function handle(PublishTemplateCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'communications.templates.manage');
        $current = $this->repository->lock($command->id) ?? throw CommunicationException::missing('Template');
        CommunicationRules::version($current->version, $command->expectedVersion);
        $now = $this->clock->now();
        $template = $current->publish($command->actorId, $now);
        foreach ($this->policies as $policy) {
            $problem = $policy->violation($template->key, $template->content->subject, $template->content->html, $template->content->variables);
            if (null !== $problem) {
                throw CommunicationException::invalid($problem);
            }
        }
        $this->repository->save($template);
        $this->repository->publish($template);
        $this->audit->record($command->actorId, 'template.publishtemplate', $template->id, '', $now);

        return $template->id;
    }
}
