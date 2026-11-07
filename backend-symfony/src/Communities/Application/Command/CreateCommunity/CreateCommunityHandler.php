<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\CreateCommunity;

use App\Communities\Application\Contract\CommunityAuditWriter;
use App\Communities\Domain\Community;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\Repository\CommunityRepository;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class CreateCommunityHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private CommunityRepository $communities,
        private CommunityAuditWriter $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(CreateCommunityCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'ministries.manage');
        if ($command->parentId !== null) {
            $parent = $this->communities->lockCommunity($command->parentId);
            if ($parent === null || $parent->kind !== 'MINISTRY' || !$parent->active) {
                throw CommunityException::invalid('Choose an active parent ministry.');
            }
        }
        $community = Community::reconstitute(
            $command->id,
            $command->kind,
            $command->name,
            $command->description,
            $command->parentId,
            $command->campus,
            true,
            0
        );
        $this->communities->insertCommunity($community);
        $this->audit->record($community->id, $community->id, 'CREATED', '', $command->actorId, $this->clock->now());

        return $community->id;
    }
}
