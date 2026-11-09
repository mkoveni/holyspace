<?php

declare(strict_types=1);

namespace App\Media\Application;

use App\Media\Application\Port\DeletionQueue;
use App\Media\Application\Port\FileStore;
use App\Media\Domain\ImageKind;
use App\Media\Domain\MediaFile;

/**
 * Removes the bytes of pictures that were replaced or cleared, after the change committed. Safe to run twice at once
 * or after a crash: deleting a missing file is not an error and a queue row is only dropped once its files are gone.
 */
final readonly class DeletionDrain
{
    public function __construct(private DeletionQueue $queue, private FileStore $files)
    {
    }

    /** @return int how many queued pictures were removed */
    public function run(int $limit = 100): int
    {
        $removed = 0;
        foreach ($this->queue->next($limit) as $pending) {
            $kind = ImageKind::tryFrom(explode('/', $pending->path)[0]);
            $this->files->delete($pending->path);
            foreach ($kind?->policy()->variantWidths ?? [] as $width) {
                $this->files->delete(MediaFile::variantOf($pending->path, $width));
            }
            $this->queue->finish($pending->fileId);
            ++$removed;
        }

        return $removed;
    }
}
