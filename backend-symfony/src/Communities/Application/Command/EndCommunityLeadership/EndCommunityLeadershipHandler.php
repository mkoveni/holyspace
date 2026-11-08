<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\EndCommunityLeadership;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\CommunityAuditWriter;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityRules;
use App\Communities\Domain\Repository\CommunityRepository;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Ends an appointment now; the membership continues. Lock order: the person, then the community. */
final readonly class EndCommunityLeadershipHandler implements CommandHandler
{
    public function __construct(
        private CommunityAccess $access,
        private PersonDirectory $people,
        private CommunityRepository $communities,
        private CommunityAuditWriter $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(EndCommunityLeadershipCommand $command): void
    {
        $this->access->manager($command->actorId);
        $reason = CommunityRules::text($command->reason, 2000);
        $before = $this->communities->findLeadershipUnlocked($command->leadershipId) ?? throw CommunityException::missing();
        $membership = $this->communities->findMembershipUnlocked($before->membershipId) ?? throw CommunityException::missing();
        $this->people->lock($membership->personId) ?? throw CommunityException::missing();
        $this->communities->lockCommunity($before->communityId) ?? throw CommunityException::missing();
        $this->access->manager($command->actorId);
        $leadership = $this->communities->findLeadershipUnlocked($command->leadershipId) ?? throw CommunityException::missing();
        CommunityRules::version($leadership->version, $command->expectedVersion);
        $now = $this->clock->now();
        $this->communities->saveLeadership($leadership->end($now));
        $this->audit->record($leadership->communityId, $leadership->id, 'LEADERSHIP_ENDED', $reason, $command->actorId, $now);
    }
}
