<?php

declare(strict_types=1);

namespace App\Media\Application\Port;

use App\Media\Domain\MediaFile;
use DateTimeImmutable;

/**
 * Files that stop being referenced in a transaction. The row commits with the change and a scheduled task deletes the
 * file (and its smaller copies) afterwards, so a rollback never destroys a file that is still in use.
 */
interface PendingDeletions
{
    public function queue(MediaFile $file, DateTimeImmutable $at): void;
}
