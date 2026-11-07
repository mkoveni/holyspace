<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Safeguarding\Domain\Repository;

use App\ChildrenMinistry\Safeguarding\Domain\SupportRecord;
use App\Shared\Domain\CalendarDate;

/** Write side of confidential support records. */
interface SupportRepository
{
    public function supportRecordForUpdate(string $childId): ?SupportRecord;

    public function insertSupportRecord(SupportRecord $record): void;

    public function updateSupportRecord(SupportRecord $record): void;

    /** Deletes the record only if its retention ended before the date. */
    public function purge(string $childId, CalendarDate $before): void;
}
