<?php

declare(strict_types=1);

namespace App\People\Application\Command\CreateRelationship;

use App\People\Domain\Enum\RelationshipType;
use App\People\Domain\Model\PersonRelationship;
use App\People\Domain\Repository\PersonRepository;
use App\People\Domain\Repository\RelationshipRepository;
use App\People\Domain\ValueObject\PersonId;
use App\People\Domain\ValueObject\RelationshipId;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateRelationshipHandler
{
    public function __construct(private RelationshipRepository $relationships, private PersonRepository $people)
    {
    }

    public function __invoke(CreateRelationshipCommand $c): RelationshipId
    {
        $personId = PersonId::fromString($c->personId);
        $relatedPersonId = PersonId::fromString($c->relatedPersonId);
        if (!$this->people->exists($personId) || !$this->people->exists($relatedPersonId)) {
            throw new RuntimeException('Both people must exist.');
        }
        $id = RelationshipId::generate();
        $this->relationships->save(PersonRelationship::create($id, $personId, $relatedPersonId, RelationshipType::from($c->type)));

        return $id;
    }
}
