<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Reporting\Application;

final readonly class ReportSummary
{
    public function __construct(public int $visits, public int $children, public int $attending, public int $collected, public int $transferred, public int $supervisorCollections)
    {
    }
}
