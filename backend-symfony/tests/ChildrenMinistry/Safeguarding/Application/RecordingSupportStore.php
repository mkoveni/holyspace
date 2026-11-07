<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Safeguarding\Application;

use App\ChildrenMinistry\Safeguarding\Application\Contract\SupportReader;
use App\ChildrenMinistry\Safeguarding\Application\Contract\SupportSessionScope;
use App\ChildrenMinistry\Safeguarding\Application\SupportRecordView;
use App\ChildrenMinistry\Safeguarding\Domain\Repository\SupportRepository;
use App\ChildrenMinistry\Safeguarding\Domain\SupportRecord;
use App\Shared\Domain\CalendarDate;
use App\Tests\ChildrenMinistry\Shared\CallLog;

/** In-memory support store and session scope that log every call. */
final class RecordingSupportStore implements SupportRepository, SupportReader, SupportSessionScope
{
    /** @var array<string, SupportRecord> */
    public array $records = [];
    /** @var list<string> child ids returned as expired, even if the locked row says otherwise */
    public array $listedExpired = [];

    public function __construct(private readonly CallLog $log)
    {
    }

    public function supportRecordForUpdate(string $childId): ?SupportRecord
    {
        $this->log->entries[] = 'forUpdate:support';

        return $this->records[$childId] ?? null;
    }

    public function insertSupportRecord(SupportRecord $record): void
    {
        $this->log->entries[] = 'insert:support';
        $this->records[$record->childId] = $record;
    }

    public function updateSupportRecord(SupportRecord $record): void
    {
        $this->log->entries[] = 'update:support';
        $this->records[$record->childId] = $record;
    }

    public function purge(string $childId, CalendarDate $before): void
    {
        $this->log->entries[] = 'purge:'.$childId;
        unset($this->records[$childId]);
    }

    public function supportRecord(string $childId): ?SupportRecordView
    {
        $this->log->entries[] = 'read:support';
        $r = $this->records[$childId] ?? null;

        return null === $r ? null : new SupportRecordView($r->childId, $r->allergies, $r->careInstructions, $r->restrictedNotes, $r->reviewOn, $r->retainUntil, $r->version, $r->updatedAt);
    }

    public function expiredSupport(CalendarDate $date, int $limit): array
    {
        $this->log->entries[] = 'list:expired';

        return $this->listedExpired;
    }

    public function require(string $actorId, string $sessionId, string $childId): void
    {
        $this->log->entries[] = 'scope:'.$sessionId;
    }
}
