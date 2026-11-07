<?php

declare(strict_types=1);

namespace App\People\Application\Query\GetPersonRelationships;

use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\People\Application\Contract\PersonReader;
use App\People\Application\Contract\RelationshipReader;
use App\People\Application\RelationshipView;
use App\People\Domain\PeopleException;

final readonly class GetPersonRelationshipsHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private PersonReader $persons,
        private RelationshipReader $links,
    ) {
    }

    /** @return list<RelationshipView> */
    public function handle(GetPersonRelationshipsQuery $query): array
    {
        $this->authorization->requirePermission('people.read');
        if (!$this->persons->exists($query->personId)) {
            throw PeopleException::missing('Person');
        }

        return $this->links->forPerson($query->personId);
    }
}
