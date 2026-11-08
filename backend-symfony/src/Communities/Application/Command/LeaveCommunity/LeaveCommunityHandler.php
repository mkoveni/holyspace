<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\LeaveCommunity;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\CommunityAuditWriter;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityRules;
use App\Communities\Domain\Repository\CommunityRepository;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Ends a membership now, and any current appointment on it, which also ends that leader's scoped access. */
final readonly class LeaveCommunityHandler implements CommandHandler
{
    public function __construct(
        private CommunityAccess $access,
        private PersonDirectory $people,
        private CommunityRepository $communities,
        private CommunityAuditWriter $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(LeaveCommunityCommand $command): void
    {
        $this->access->manager($command->actorId);
        $reason = CommunityRules::text($command->reason, 2000);
        $before = $this->communities->findMembershipUnlocked($command->membershipId) ?? throw CommunityException::missing();
        $this->people->lock($before->personId) ?? throw CommunityException::missing();
        $this->communities->lockCommunity($before->communityId) ?? throw CommunityException::missing();
        $this->access->manager($command->actorId);
        $membership = $this->communities->findMembershipUnlocked($command->membershipId) ?? throw CommunityException::missing();
        CommunityRules::version($membership->version, $command->expectedVersion);
        $now = $this->clock->now();
        $this->communities->saveMembership($membership->end($now));
        $leadership = $this->communities->activeLeadership($membership->id);
        if (null !== $leadership) {
            $this->communities->saveLeadership($leadership->end($now));
        }
        $this->audit->record($membership->communityId, $membership->id, 'MEMBER_LEFT', $reason, $command->actorId, $now);
    }
}
