<?php

declare(strict_types=1);

namespace App\Communities\Application\Command\CreateCommunity;

use App\Communities\Application\CommunityAccess;
use App\Communities\Application\CommunityHash;
use App\Communities\Application\Contract\CommunityAuditWriter;
use App\Communities\Application\RequestReplay;
use App\Communities\Domain\Community;
use App\Communities\Domain\CommunityException;
use App\Communities\Domain\Repository\CommunityRepository;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/** Creates a ministry or group (a group may sit under an active ministry, whose row is locked). */
final readonly class CreateCommunityHandler implements CommandHandler
{
    public function __construct(
        private CommunityAccess $access,
        private CommunityRepository $communities,
        private RequestReplay $requests,
        private CommunityAuditWriter $audit,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    public function handle(CreateCommunityCommand $command): string
    {
        $this->access->manager($command->actorId);
        $community = Community::create($this->ids->uuid(), $command->kind, $command->name, $command->description, $command->parentId, $command->campus);
        $hash = CommunityHash::of($command->actorId, 'CREATE', $command->kind, $command->name, $command->description, $command->parentId, $command->campus);
        $previous = $this->requests->previous($command->requestId, $hash);
        if (null !== $previous) {
            return $previous;
        }
        if (null !== $command->parentId) {
            $ministry = $this->communities->lockCommunity($command->parentId) ?? throw CommunityException::missing();
            if (Community::MINISTRY !== $ministry->kind) {
                throw CommunityException::invalid('Choose a parent ministry.');
            }
            $ministry->requireActive();
        }
        $this->communities->insertCommunity($community);
        $this->requests->record((string) $command->requestId, $hash, $community->id);
        $this->audit->record($community->id, $community->id, 'CREATED', '', $command->actorId, $this->clock->now());

        return $community->id;
    }
}
