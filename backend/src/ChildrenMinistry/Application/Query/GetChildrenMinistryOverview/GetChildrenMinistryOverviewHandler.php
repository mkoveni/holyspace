<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Application\Query\GetChildrenMinistryOverview;

use App\ChildrenMinistry\Application\ReadModel\ChildrenMinistryReadStore;

final readonly class GetChildrenMinistryOverviewHandler
{
    public function __construct(private ChildrenMinistryReadStore $records)
    {
    }

    public function __invoke(GetChildrenMinistryOverviewQuery $message): array
    {
        $result = [];
        foreach (['programmes', 'classes', 'enrolments', 'authorisations', 'sessions', 'checkins'] as $kind) {
            $result[$kind] = $this->records->all($kind);
        }

        return $result;
    }
}
