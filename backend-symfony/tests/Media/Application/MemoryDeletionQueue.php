<?php

declare(strict_types=1);

namespace App\Tests\Media\Application;

use App\Media\Application\Port\DeletionQueue;
use App\Media\Application\Port\PendingFile;

final class MemoryDeletionQueue implements DeletionQueue
{
    /** @var list<string> */
    public array $finished = [];

    /** @param list<PendingFile> $pending */
    public function __construct(public array $pending = [])
    {
    }

    public function next(int $limit): array
    {
        return array_slice(array_values(array_filter($this->pending, fn (PendingFile $file): bool => !in_array($file->fileId, $this->finished, true))), 0, $limit);
    }

    public function finish(string $fileId): void
    {
        $this->finished[] = $fileId;
    }
}
