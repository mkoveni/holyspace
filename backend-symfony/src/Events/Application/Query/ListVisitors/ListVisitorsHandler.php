<?php

declare(strict_types=1);

namespace App\Events\Application\Query\ListVisitors;

use App\Events\Application\Contract\OccurrenceReader;
use App\Events\Application\Contract\VisitorPage;
use App\Events\Application\Contract\VisitorReader;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\Events\Domain\VisitorCheckIn;
use App\IdentityAccess\Application\Contract\PermissionChecker;

/** Contact details of visitors need their own permission, separate from reading attendance. */
final readonly class ListVisitorsHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private OccurrenceReader $occurrences,
        private VisitorReader $visitors,
    ) {
    }

    public function handle(ListVisitorsQuery $query): VisitorPage
    {
        $this->authorization->requirePermission('events.visitors.read');
        EventRules::page($query->limit, $query->offset);
        if (null !== $query->reviewStatus && !\in_array($query->reviewStatus, [VisitorCheckIn::PENDING, VisitorCheckIn::CONFIRMED, VisitorCheckIn::REMOVED], true)) {
            throw EventException::invalid('Choose pending, confirmed or removed.');
        }
        $occurrence = $this->occurrences->find($query->occurrenceId) ?? throw EventException::missing('Occurrence');

        return new VisitorPage(
            $this->visitors->forOccurrence($occurrence->id, $query->reviewStatus, $query->limit, $query->offset),
            $this->visitors->count($occurrence->id, $query->reviewStatus),
            $this->visitors->count($occurrence->id, VisitorCheckIn::PENDING),
        );
    }
}
