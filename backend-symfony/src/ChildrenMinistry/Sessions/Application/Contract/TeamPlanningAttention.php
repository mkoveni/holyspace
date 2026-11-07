<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application\Contract;

use App\Shared\Domain\CalendarDate;

/** The sessions whose planned teams need attention, published for the ministry overview. */
interface TeamPlanningAttention
{
    /**
     * The first sessions in the inclusive date range that are not ready.
     *
     * @throws \App\ChildrenMinistry\Shared\Domain\ChildrenException when more than 1,000 sessions match
     */
    public function attention(CalendarDate $from, CalendarDate $until, int $limit): TeamPlanningView;
}
