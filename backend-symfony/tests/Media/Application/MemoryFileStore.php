<?php

declare(strict_types=1);

namespace App\Tests\Media\Application;

use App\Media\Application\Port\FileStore;
use App\Media\Domain\MediaException;

final class MemoryFileStore implements FileStore
{
    /** @var array<string, string> */
    public array $files = [];
    public bool $failWrites = false;

    public function put(string $path, string $bytes): void
    {
        if ($this->failWrites) {
            throw MediaException::storageUnavailable();
        }
        $this->files[$path] = $bytes;
    }

    public function read(string $path): string
    {
        return $this->files[$path] ?? throw MediaException::missing();
    }

    public function delete(string $path): void
    {
        unset($this->files[$path]);
    }

    /** @var array<string, int> modified times for paths that should look old; others count as just written */
    public array $modified = [];
    public int $temporaryPurged = 0;
    public ?int $purgedBefore = null;

    public function all(): iterable
    {
        foreach ($this->files as $path => $bytes) {
            yield $path => $this->modified[$path] ?? PHP_INT_MAX;
        }
    }

    public function purgeTemporary(int $olderThan): int
    {
        $this->purgedBefore = $olderThan;

        return $this->temporaryPurged;
    }
}
