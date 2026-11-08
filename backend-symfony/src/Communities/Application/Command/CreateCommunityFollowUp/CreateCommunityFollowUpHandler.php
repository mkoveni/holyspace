<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\CreateCommunityFollowUp;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\CommunityHash;
use App\Communities\Application\Contract\CommunityAuditWriter;
use App\Communities\Application\RequestReplay;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityRules;
use App\Communities\Domain\FollowUp;
use App\Communities\Domain\Repository\CommunityRepository;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * Records a follow-up for a current member. Managers, and leaders of this community, may do it.
 * Lock order: the person, then the community.
 */
final readonly class CreateCommunityFollowUpHandler implements CommandHandler
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

    public function handle(CreateCommunityFollowUpCommand $command): string
    {
        $this->access->actor($command->actorId);
        $note = CommunityRules::text($command->note, 2000);
        $due = $command->dueOn ?? throw CommunityException::invalid('Choose a follow-up date.');
        $hash = CommunityHash::of($command->actorId, 'FOLLOWUP', $command->communityId, $command->personId, $note, $due->iso());
        $person = $this->people->lock($command->personId);
        if (null === $person || !$person->available) {
            throw CommunityException::missing();
        }
        $this->communities->lockCommunity($command->communityId) ?? throw CommunityException::missing();
        $this->access->actor($command->actorId);
        $this->access->require($command->communityId);
        $previous = $this->requests->previous($command->requestId, $hash, RequestReplay::ALREADY_USED);
        if (null !== $previous) {
            return $previous;
        }
        $now = $this->clock->now();
        if (!$this->access->community($command->communityId)->active
            || null === $this->communities->currentMembership($command->communityId, $command->personId, $now)) {
            throw CommunityException::conflict('Follow-up requires a current member of an active community.');
        }
        $followUp = FollowUp::open($this->ids->uuid(), $command->communityId, $command->personId, $note, $due, $command->actorId, $now);
        $this->communities->insertFollowUp($followUp);
        $this->requests->record((string) $command->requestId, $hash, $followUp->id);
        $this->audit->record($command->communityId, $followUp->id, 'FOLLOWUP_CREATED', 'Member follow-up recorded.', $command->actorId, $now);

        return $followUp->id;
    }
}
