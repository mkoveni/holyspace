<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\GetSessionSeries;

use App\ChildrenMinistry\Sessions\Application\Contract\SessionSeriesReader;
use App\ChildrenMinistry\Sessions\Application\SeriesDetail;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetSessionSeriesHandler
{
    public function __construct(private PermissionChecker $authorization, private SessionSeriesReader $reader)
    {
    }

    public function handle(GetSessionSeriesQuery $query): SeriesDetail
    {
        $this->authorization->requirePermission('children.read');

        return new SeriesDetail($this->reader->summary($query->id) ?? throw ChildrenException::missing('Series'), $this->reader->sessions($query->id));
    }
}
