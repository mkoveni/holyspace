<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Command\PublishConsentPolicy;

use App\ChildrenMinistry\Participation\Domain\Repository\ParticipationRepository;
use App\ChildrenMinistry\Safeguarding\Domain\ConsentPolicy;
use App\ChildrenMinistry\Safeguarding\Domain\Repository\SafeguardingRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class PublishConsentPolicyHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
        private ParticipationRepository $programmes,
        private SafeguardingRepository $repository,
    ) {
    }

    /** @return string the new policy id; it becomes the programme's current policy */
    public function handle(PublishConsentPolicyCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.manage');

        $programme = $this->programmes->programmeForUpdate($command->programmeId) ?? throw ChildrenException::missing('Programme');
        $now = $this->clock->now();
        $id = $this->ids->uuid();
        $updated = $programme->withPolicy($id, $command->expectedVersion);
        $policy = ConsentPolicy::publish($id, $programme->id, $command->label, $command->documentReference, $command->contentHash, $now);
        $this->repository->insertConsentPolicy($policy);
        $this->programmes->updateProgramme($updated);
        $this->audit->record($command->actorId, 'consent.policy.published', $policy->id, '', $now);

        return $policy->id;
    }
}
