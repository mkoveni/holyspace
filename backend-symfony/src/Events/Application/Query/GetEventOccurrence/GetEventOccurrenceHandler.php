<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetEventOccurrence;

use App\Events\Application\Contract\EventReader;
use App\Events\Application\Contract\OccurrenceDetails;
use App\Events\Application\Contract\OccurrenceReader;
use App\Events\Application\Contract\RegistrationReader;
use App\Events\Domain\EventException;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetEventOccurrenceHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private OccurrenceReader $occurrences,
        private EventReader $events,
        private RegistrationReader $registrations,
    ) {
    }

    public function handle(GetEventOccurrenceQuery $query): OccurrenceDetails
    {
        $this->authorization->requirePermission('events.read');
        $entry = $this->occurrences->entry($query->occurrenceId) ?? throw EventException::missing('Occurrence');
        $occurrence = $entry->occurrence;

        return new OccurrenceDetails(
            $occurrence,
            $entry->title,
            $entry->type,
            $this->events->findSeries($occurrence->seriesId) ?? throw EventException::missing('Series'),
            $this->registrations->policy($occurrence->id) ?? throw EventException::missing('Registration policy'),
            $this->registrations->counts($occurrence->id),
        );
    }
}
