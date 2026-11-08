<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\AppointCommunityLeader;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\CommunityHash;
use App\Communities\Application\Contract\CommunityAuditWriter;
use App\Communities\Application\RequestReplay;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityRules;
use App\Communities\Domain\Leadership;
use App\Communities\Domain\Repository\CommunityRepository;
use App\IdentityAccess\Application\Contract\AccountDirectory;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * Appoints a leader on a current membership. Binding a login account grants that account scoped access, so it also
 * needs live `identity.manage`. Lock order: the person, then the community.
 */
final readonly class AppointCommunityLeaderHandler implements CommandHandler
{
    public function __construct(
        private CommunityAccess $access,
        private PermissionChecker $auth,
        private AccountDirectory $accounts,
        private PersonDirectory $people,
        private CommunityRepository $communities,
        private RequestReplay $requests,
        private CommunityAuditWriter $audit,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    public function handle(AppointCommunityLeaderCommand $command): string
    {
        $this->access->manager($command->actorId);
        $reason = CommunityRules::text($command->reason, 2000);
        $hash = CommunityHash::of($command->actorId, 'APPOINT', $command->membershipId, $command->expectedVersion, $command->role, $command->accountId, $reason);
        $before = $this->communities->findMembershipUnlocked($command->membershipId) ?? throw CommunityException::missing();
        $person = $this->people->lock($before->personId);
        if (null === $person || !$person->available) {
            throw CommunityException::missing();
        }
        $community = $this->communities->lockCommunity($before->communityId) ?? throw CommunityException::missing();
        $this->access->manager($command->actorId);
        $previous = $this->requests->previous($command->requestId, $hash);
        if (null !== $previous) {
            return $previous;
        }
        if (null !== $command->accountId) {
            $this->auth->requireActorPermission($command->actorId, 'identity.manage');
            if (!$this->accounts->active($command->accountId)) {
                throw CommunityException::invalid('Choose an active login account.');
            }
        }
        $community->requireActive();
        $membership = $this->communities->findMembershipUnlocked($command->membershipId) ?? throw CommunityException::missing();
        CommunityRules::version($membership->version, $command->expectedVersion);
        $now = $this->clock->now();
        if (!$membership->covers($now)) {
            throw CommunityException::conflict('Leadership requires a current membership.');
        }
        if (null !== $this->communities->activeLeadership($membership->id)) {
            throw CommunityException::conflict('End the current appointment before creating another.');
        }
        $leadership = Leadership::appoint($this->ids->uuid(), $membership->id, $community->id, $command->accountId, $command->role, $now);
        $this->communities->insertLeadership($leadership);
        $this->requests->record((string) $command->requestId, $hash, $leadership->id);
        $this->audit->record($community->id, $leadership->id, 'LEADER_APPOINTED', $reason, $command->actorId, $now);

        return $leadership->id;
    }
}
