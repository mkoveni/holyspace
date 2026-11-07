<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetEventOccurrenceHistory;

use App\Events\Application\Contract\EventJournalReader;
use App\Events\Application\Contract\OccurrenceHistoryEntry;
use App\Events\Application\Contract\OccurrenceReader;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetEventOccurrenceHistoryHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private OccurrenceReader $occurrences,
        private EventJournalReader $journal,
    ) {
    }

    /** @return list<OccurrenceHistoryEntry> */
    public function handle(GetEventOccurrenceHistoryQuery $query): array
    {
        $this->authorization->requirePermission('events.read');
        EventRules::page($query->limit, $query->offset);
        $this->occurrences->find($query->occurrenceId) ?? throw EventException::missing('Occurrence');

        return $this->journal->occurrenceHistory($query->occurrenceId, $query->limit, $query->offset);
    }
}
