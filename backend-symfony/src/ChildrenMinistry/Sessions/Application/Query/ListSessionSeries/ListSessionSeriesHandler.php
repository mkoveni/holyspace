<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\ListSessionSeries;

use App\ChildrenMinistry\Sessions\Application\Contract\SessionSeriesReader;
use App\ChildrenMinistry\Sessions\Application\SeriesPage;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class ListSessionSeriesHandler
{
    public function __construct(private PermissionChecker $authorization, private SessionSeriesReader $reader)
    {
    }

    public function handle(ListSessionSeriesQuery $query): SeriesPage
    {
        $this->authorization->requirePermission('children.read');
        ChildrenRules::page($query->limit, $query->offset);

        return $this->reader->list($query->limit, $query->offset);
    }
}
