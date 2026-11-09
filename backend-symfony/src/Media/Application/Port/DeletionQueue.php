<?php

declare(strict_types=1);

namespace App\Media\Application\Port;

/** The draining side of `PendingDeletions`, used by the scheduled task. */
interface DeletionQueue
{
    /** @return list<PendingFile> the oldest queued files, at most `$limit` */
    public function next(int $limit): array;

    /** Forgets a file whose bytes are gone: removes its queue row and its `media_files` row if no slot refers to it. */
    public function finish(string $fileId): void;
}
