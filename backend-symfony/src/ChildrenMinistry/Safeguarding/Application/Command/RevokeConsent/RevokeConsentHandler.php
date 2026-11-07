<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Application\Command\RevokeConsent;

use App\ChildrenMinistry\Safeguarding\Application\Contract\SafeguardingReader;
use App\ChildrenMinistry\Safeguarding\Domain\Repository\SafeguardingRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Application\ParticipantAccess;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class RevokeConsentHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private SafeguardingRepository $repository,
        private SafeguardingReader $safeguarding,
        private ParticipantAccess $participants,
    ) {
    }

    public function handle(RevokeConsentCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.safeguarding');

        $source = $this->safeguarding->consent($command->id) ?? throw ChildrenException::missing('Consent');
        $this->participants->lockChild($source->childId);
        $now = $this->clock->now();
        $consent = $this->repository->consentForUpdate($command->id) ?? throw ChildrenException::missing('Consent');
        $this->repository->updateConsent($consent->revoke($command->expectedVersion, $command->reason, $now));
        $this->audit->record($command->actorId, 'consent.revoked', $consent->id, ChildrenRules::text($command->reason, 500), $now);
    }
}
