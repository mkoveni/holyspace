<?php

declare(strict_types=1);

namespace App\People\Application\Query\FamilyOverview;

use App\Shared\Application\CQRS\Query;

final readonly class FamilyOverviewQuery implements Query
{
    public function __construct(public ?string $familyId = null)
    {
    }
}
