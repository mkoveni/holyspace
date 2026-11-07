<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetEventRegistrationHistory;

use App\Events\Application\Contract\EventJournalReader;
use App\Events\Application\Contract\RegistrationHistoryEntry;
use App\Events\Application\Contract\RegistrationReader;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\IdentityAccess\Application\Contract\PermissionChecker;

final readonly class GetEventRegistrationHistoryHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private RegistrationReader $registrations,
        private EventJournalReader $journal,
    ) {
    }

    /** @return list<RegistrationHistoryEntry> */
    public function handle(GetEventRegistrationHistoryQuery $query): array
    {
        $this->authorization->requirePermission('events.registrations.read');
        EventRules::page($query->limit, $query->offset);
        $this->registrations->find($query->registrationId) ?? throw EventException::missing('Registration');

        return $this->journal->registrationHistory($query->registrationId, $query->limit, $query->offset);
    }
}
