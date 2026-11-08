<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\CompleteCommunityFollowUp;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\CommunityAuditWriter;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityRules;
use App\Communities\Domain\Repository\CommunityRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Completes an open follow-up with a reason; the community row is locked first. */
final readonly class CompleteCommunityFollowUpHandler implements CommandHandler
{
    public function __construct(
        private CommunityAccess $access,
        private CommunityRepository $communities,
        private CommunityAuditWriter $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(CompleteCommunityFollowUpCommand $command): void
    {
        $this->access->actor($command->actorId);
        $reason = CommunityRules::text($command->reason, 2000);
        $before = $this->communities->findFollowUpUnlocked($command->followUpId) ?? throw CommunityException::missing();
        $this->communities->lockCommunity($before->communityId) ?? throw CommunityException::missing();
        $this->access->actor($command->actorId);
        $this->access->require($before->communityId);
        $followUp = $this->communities->findFollowUpUnlocked($command->followUpId) ?? throw CommunityException::missing();
        CommunityRules::version($followUp->version, $command->expectedVersion);
        $this->communities->saveFollowUp($followUp->complete());
        $this->audit->record($followUp->communityId, $followUp->id, 'FOLLOWUP_COMPLETED', $reason, $command->actorId, $this->clock->now());
    }
}
