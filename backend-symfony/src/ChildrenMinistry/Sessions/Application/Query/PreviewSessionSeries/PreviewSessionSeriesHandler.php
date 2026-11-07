<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Query\PreviewSessionSeries;

use App\ChildrenMinistry\Sessions\Application\SeriesPreview;
use App\ChildrenMinistry\Sessions\Application\SessionSeriesPlanning;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class PreviewSessionSeriesHandler
{
    public function __construct(private PermissionChecker $authorization, private SessionSeriesPlanning $planning)
    {
    }

    public function handle(PreviewSessionSeriesQuery $query): SeriesPreview
    {
        $this->authorization->requirePermission('children.manage');

        return $this->planning->preview($query->draft);
    }
}
