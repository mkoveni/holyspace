<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application\Command\CreateGuardianCommunication;

use App\ChildrenMinistry\Messaging\Application\Contract\GuardianCommunicationReader;
use App\ChildrenMinistry\Messaging\Application\GuardianCommunicationPolicy;
use App\ChildrenMinistry\Messaging\Domain\GuardianCommunication;
use App\ChildrenMinistry\Messaging\Domain\Repository\GuardianCommunicationRepository;
use App\ChildrenMinistry\Safeguarding\Domain\Repository\SafeguardingRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Application\RequestFingerprint;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * Prepares a draft message. Nothing is sent. Lock order: the child's person row, then the guardian authority. A retry with
 * the same request id returns the same draft; a changed payload under that id is a conflict.
 */
final readonly class CreateGuardianCommunicationHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private GuardianCommunicationPolicy $policy,
        private GuardianCommunicationReader $reader,
        private GuardianCommunicationRepository $repository,
        private SafeguardingRepository $safeguarding,
        private PersonDirectory $people,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private ChurchCalendar $calendar,
        private IdGenerator $ids,
    ) {
    }

    /** @return string the communication id */
    public function handle(CreateGuardianCommunicationCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.safeguarding');
        $hash = RequestFingerprint::of($command->childId, $command->authorityId, $command->programmeId, $command->sessionId, $command->type, $command->dueOn->iso());

        $this->people->lock($command->childId) ?? throw ChildrenException::missing('Child');
        $this->safeguarding->guardianAuthorityForUpdate($command->authorityId) ?? throw ChildrenException::missing('Guardian authority');
        $previous = $this->reader->receipt($command->actorId, $command->requestId);
        if (null !== $previous) {
            if ($previous->hash !== $hash) {
                throw ChildrenException::conflict('This communication request was already used for different details.');
            }

            return $previous->id;
        }

        $now = $this->clock->now();
        $today = $this->calendar->today();
        if ($command->dueOn->isBefore($today)) {
            throw ChildrenException::invalid('Choose a current or future contact date.');
        }
        $guardianId = $this->policy->guardian($command->childId, $command->authorityId, $now);
        $type = (string) $command->type;
        $programme = $this->policy->programme($command->programmeId, $command->childId, $type, $command->sessionId, $now);
        if ('SESSION_REMINDER' === $type && $command->dueOn->isAfter($today->plusDays(365))) {
            throw ChildrenException::invalid('Choose a contact date within the next year.');
        }
        $this->policy->contactDate($command->sessionId, $command->dueOn);

        $communication = GuardianCommunication::prepare(
            $this->ids->uuid(),
            $command->childId,
            $command->authorityId,
            $guardianId,
            $command->programmeId,
            $programme->currentPolicyId,
            $command->sessionId,
            $command->type,
            $this->policy->message($command->childId, $guardianId, $programme, $type, $command->sessionId),
            $command->dueOn,
            $command->actorId,
            $now,
        );
        $this->repository->insert($communication, $command->requestId, $hash);
        $this->audit->record($command->actorId, 'guardian.communication.prepared', $communication->id, '', $now);

        return $communication->id;
    }
}
