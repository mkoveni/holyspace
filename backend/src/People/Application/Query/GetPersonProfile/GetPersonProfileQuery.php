<?php

declare(strict_types=1);

namespace App\People\Application\Query\GetPersonProfile;

use App\Shared\Application\CQRS\Query;

final readonly class GetPersonProfileQuery implements Query
{
    public function __construct(public string $personId)
    {
    }
}
