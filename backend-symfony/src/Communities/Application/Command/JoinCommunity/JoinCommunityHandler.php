<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\JoinCommunity;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\CommunityHash;
use App\Communities\Application\Contract\CommunityAuditWriter;
use App\Communities\Application\RequestReplay;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\Membership;
use App\Communities\Domain\Repository\CommunityRepository;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/** Starts a membership now. Lock order: the person, then the community, so parallel joins of one person serialize. */
final readonly class JoinCommunityHandler implements CommandHandler
{
    public function __construct(
        private CommunityAccess $access,
        private PersonDirectory $people,
        private CommunityRepository $communities,
        private RequestReplay $requests,
        private CommunityAuditWriter $audit,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    public function handle(JoinCommunityCommand $command): string
    {
        $this->access->manager($command->actorId);
        $hash = CommunityHash::of($command->actorId, 'JOIN', $command->communityId, $command->personId);
        $person = $this->people->lock($command->personId);
        if (null === $person || !$person->available) {
            throw CommunityException::invalid('Choose an available person.');
        }
        $community = $this->communities->lockCommunity($command->communityId) ?? throw CommunityException::missing();
        $this->access->manager($command->actorId);
        $previous = $this->requests->previous($command->requestId, $hash);
        if (null !== $previous) {
            return $previous;
        }
        $community->requireActive();
        $now = $this->clock->now();
        if (null !== $this->communities->currentMembership($community->id, $command->personId, $now)) {
            throw CommunityException::conflict('This person is already a current member.');
        }
        $membership = Membership::start($this->ids->uuid(), $community->id, $command->personId, $now);
        $this->communities->insertMembership($membership);
        $this->requests->record((string) $command->requestId, $hash, $membership->id);
        $this->audit->record($community->id, $membership->id, 'MEMBER_JOINED', '', $command->actorId, $now);

        return $membership->id;
    }
}
