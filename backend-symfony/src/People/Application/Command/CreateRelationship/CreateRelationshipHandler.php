<?php

declare(strict_types=1);

namespace App\People\Application\Command\CreateRelationship;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PeopleAuditWriter;
use App\People\Domain\ParentagePolicy;
use App\People\Domain\PeopleException;
use App\People\Domain\PersonRelationship;
use App\People\Domain\Repository\PersonRepository;
use App\People\Domain\Repository\RelationshipRepository;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

final readonly class CreateRelationshipHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private PeopleAuditWriter $audit,
        private ClockInterface $clock,
        private RelationshipRepository $relationships,
        private PersonRepository $people,
        private IdGenerator $ids,
    ) {
    }

    /** @return string the new relationship id */
    public function handle(CreateRelationshipCommand $command): string
    {
        $this->authorization->requireActorPermission($command->actorId, 'people.manage');

        $this->relationships->lockGraph();
        $now = $this->clock->now();
        $ids = array_unique([$command->sourceId, $command->targetId]);
        sort($ids, SORT_STRING); // a stable order avoids deadlocks between concurrent writers
        foreach ($ids as $id) {
            ($this->people->findForUpdate($id) ?? throw PeopleException::missing('Person'))->requireAvailable();
        }
        $relationship = PersonRelationship::create($this->ids->uuid(), $command->sourceId, $command->targetId, $command->type, $now);
        ParentagePolicy::requireAcyclic($relationship, $this->relationships->currentParentage($now), $now);
        $this->relationships->insert($relationship);
        $this->audit->audit($command->actorId, 'relationship.created', $relationship->id, $now);

        return $relationship->id;
    }
}
