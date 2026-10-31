<?php

declare(strict_types=1);

namespace App\People\Application\Query\GetPersonRelationships;

use App\People\Application\DTO\RelationshipView;
use App\People\Application\ReadModel\PeopleReadRepository;

final readonly class GetPersonRelationshipsHandler
{
    public function __construct(private PeopleReadRepository $read)
    {
    }

    /** @return list<RelationshipView> */
    public function __invoke(GetPersonRelationshipsQuery $q): array
    {
        return array_map(RelationshipView::fromRow(...), $this->read->relationshipsForPerson($q->personId));
    }
}
