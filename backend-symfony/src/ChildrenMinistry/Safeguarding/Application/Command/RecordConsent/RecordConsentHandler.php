<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Command\RecordConsent;

use App\ChildrenMinistry\Safeguarding\Domain\Consent;
use App\ChildrenMinistry\Safeguarding\Domain\Repository\SafeguardingRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class RecordConsentHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
        private SafeguardingRepository $repository,
        private ParticipantAccess $participants,
    ) {
    }

    /** @return string the new consent id */
    public function handle(RecordConsentCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.safeguarding');

        $this->participants->lockChild($command->childId);
        $now = $this->clock->now();
        $authority = $this->repository->findGuardianAuthority($command->authorityId) ?? throw ChildrenException::missing('GuardianAuthority');
        $policy = $this->repository->findConsentPolicy($command->policyId) ?? throw ChildrenException::missing('ConsentPolicy');

        $consent = Consent::record($this->ids->uuid(), $command->programmeId, $policy, $command->childId, $authority, $command->validFrom, $command->validUntil, $command->acceptedAt, $command->evidenceReference, $command->actorId, $now);
        $this->repository->insertConsent($consent);
        $this->audit->record($command->actorId, 'consent.recorded', $consent->id, '', $now);

        return $consent->id;
    }
}
