<?php

declare(strict_types=1);

namespace App\People\Application\Query\GetPersonRelationships;

use App\Shared\Application\CQRS\Query;

final readonly class GetPersonRelationshipsQuery implements Query
{
    public function __construct(public string $personId)
    {
    }
}
