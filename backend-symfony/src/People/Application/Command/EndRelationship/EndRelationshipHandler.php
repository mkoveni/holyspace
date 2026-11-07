<?php

declare(strict_types=1);

namespace App\People\Application\Command\EndRelationship;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PeopleAuditWriter;
use App\People\Domain\PeopleException;
use App\People\Domain\Repository\RelationshipRepository;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class EndRelationshipHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private PeopleAuditWriter $audit,
        private ClockInterface $clock,
        private RelationshipRepository $relationships,
    ) {
    }

    public function handle(EndRelationshipCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'people.manage');

        $this->relationships->lockGraph();
        $now = $this->clock->now();
        $relationship = $this->relationships->find($command->relationshipId) ?? throw PeopleException::missing('Relationship');
        $this->relationships->update($relationship->end($command->expectedVersion, $now));
        $this->audit->audit($command->actorId, 'relationship.ended', $relationship->id, $now);
    }
}
