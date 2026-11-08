<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\UpdateCommunity;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\Contract\CommunityAuditWriter;
use App\Communities\Domain\Community;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\CommunityRules;
use App\Communities\Domain\Repository\CommunityRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Renames, re-parents or archives a community with a reason. Lock order: the parent ministry before the group. */
final readonly class UpdateCommunityHandler implements CommandHandler
{
    public function __construct(
        private CommunityAccess $access,
        private CommunityRepository $communities,
        private CommunityAuditWriter $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(UpdateCommunityCommand $command): void
    {
        $this->access->manager($command->actorId);
        $reason = CommunityRules::text($command->reason, 2000);
        if (null !== $command->parentId) {
            $ministry = $this->communities->lockCommunity($command->parentId) ?? throw CommunityException::missing();
            if (Community::MINISTRY !== $ministry->kind || !$ministry->active) {
                throw CommunityException::conflict('Choose an active parent ministry.');
            }
        }
        $community = $this->communities->lockCommunity($command->communityId) ?? throw CommunityException::missing();
        $this->access->manager($command->actorId);
        CommunityRules::version($community->version, $command->expectedVersion);
        $this->communities->saveCommunity($community->update($command->name, $command->description, $command->parentId, $command->campus, $command->active));
        $this->audit->record($community->id, $community->id, $command->active ? 'UPDATED' : 'ARCHIVED', $reason, $command->actorId, $this->clock->now());
    }
}
