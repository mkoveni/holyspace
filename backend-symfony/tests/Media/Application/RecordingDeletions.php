<?php

declare(strict_types=1);

namespace App\Tests\Media\Application;

use App\Media\Application\Port\PendingDeletions;
use App\Media\Domain\MediaFile;
use DateTimeImmutable;

final class RecordingDeletions implements PendingDeletions
{
    /** @var list<MediaFile> */
    public array $queued = [];

    public function queue(MediaFile $file, DateTimeImmutable $at): void
    {
        $this->queued[] = $file;
    }
}
