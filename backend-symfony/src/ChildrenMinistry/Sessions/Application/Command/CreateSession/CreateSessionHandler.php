<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Command\CreateSession;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Participation\Application\Contract\VenueReader;
use App\ChildrenMinistry\Sessions\Domain\MinistrySession;
use App\ChildrenMinistry\Sessions\Domain\Repository\SessionRepository;
use App\ChildrenMinistry\Shared\Application\Contract\ChildrenAudit;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class CreateSessionHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private ChildrenAudit $audit,
        private ClockInterface $clock,
        private IdGenerator $ids,
        private ParticipationReader $participation,
        private VenueReader $venues,
        private SessionRepository $sessions,
    ) {
    }

    /** @return string the new session id */
    public function handle(CreateSessionCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'children.manage');
        if (null === $this->participation->ministryClass($command->classId)) {
            throw ChildrenException::missing('MinistryClass');
        }
        $room = $this->venues->room($command->roomId) ?? throw ChildrenException::missing('Room');
        if ($command->seatLimit > $room->capacity) {
            throw ChildrenException::invalid('Session seats cannot exceed room capacity.');
        }

        $now = $this->clock->now();
        $session = MinistrySession::plan($this->ids->uuid(), $command->classId, $room->id, $command->startsAt, $command->endsAt, $command->seatLimit);
        $this->sessions->insertMinistrySession($session);
        $this->audit->record($command->actorId, 'session.created', $session->id, '', $now);

        return $session->id;
    }
}
