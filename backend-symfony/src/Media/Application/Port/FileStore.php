<?php

declare(strict_types=1);

namespace App\Media\Application\Port;

/**
 * Where picture bytes live. Paths are relative and built by the domain; an implementation must refuse anything that
 * would leave its root. Writes are atomic: a reader never sees half a file.
 */
interface FileStore
{
    /** @throws \App\Media\Domain\MediaException storageUnavailable when the bytes could not be written */
    public function put(string $path, string $bytes): void;

    /** @throws \App\Media\Domain\MediaException missing when there is no such file */
    public function read(string $path): string;

    /** Removes the file; a file that is already gone is not an error. */
    public function delete(string $path): void;

    /**
     * Every stored picture file with its last-modified time (unix seconds), for the orphan sweep. Half-written
     * temporary files are not listed.
     *
     * @return iterable<string, int>
     */
    public function all(): iterable;

    /** Removes half-written temporary files last touched before the given time; returns how many. */
    public function purgeTemporary(int $olderThan): int;
}
