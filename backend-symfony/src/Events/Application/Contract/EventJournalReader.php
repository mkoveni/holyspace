<?php

declare(strict_types=1);

namespace App\Events\Application\Contract;

interface EventJournalReader
{
    public function request(string $id): ?JournalRequest;

    /** @return list<RegistrationHistoryEntry> newest first */
    public function registrationHistory(string $registrationId, int $limit, int $offset): array;

    /** @return list<OccurrenceHistoryEntry> newest version first */
    public function occurrenceHistory(string $occurrenceId, int $limit, int $offset): array;
}
