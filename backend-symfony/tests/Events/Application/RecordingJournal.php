<?php

declare(strict_types=1);

namespace App\Tests\Events\Application;

use App\Events\Application\Contract\EventJournal;
use App\Events\Application\Contract\EventJournalReader;
use App\Events\Application\Contract\JournalRequest;
use App\Events\Domain\EventOccurrence;
use DateTimeImmutable;

/** In-memory test double: no database. */
final class RecordingJournal implements EventJournal, EventJournalReader
{
    /** @var list<string> */
    public array $entries = [];
    /** @var array<string, JournalRequest> */
    public array $requests = [];

    public function record(JournalRequest $request): void
    {
        $this->requests[$request->id] = $request;
        $this->entries[] = 'request:'.$request->id;
    }

    public function audit(string $actorId, string $action, string $targetId, string $reason, DateTimeImmutable $at): void
    {
        $this->entries[] = "$actorId:$action:$targetId";
    }

    public function registration(string $actorId, string $action, string $registrationId, string $reason, DateTimeImmutable $at): void
    {
        $this->entries[] = "$actorId:registration.$action:$registrationId:$reason";
    }

    public function registrationHistory(string $registrationId, int $limit, int $offset): array
    {
        return [];
    }

    public function occurrence(string $actorId, string $action, EventOccurrence $occurrence, string $reason, DateTimeImmutable $at): void
    {
        $this->entries[] = "$actorId:occurrence.$action:$occurrence->id";
    }

    public function request(string $id): ?JournalRequest
    {
        return $this->requests[$id] ?? null;
    }

    public function occurrenceHistory(string $occurrenceId, int $limit, int $offset): array
    {
        return [];
    }
}
