<?php

declare(strict_types=1);

namespace App\Media\Infrastructure;

use App\Media\Application\Port\FileStore;
use App\Media\Domain\MediaException;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Picture files under one root directory outside the web root. Only paths of the exact shape the domain builds are
 * accepted (an allow-list, not a search for `..`). Writes go to a temporary file and are renamed into place, so a
 * reader never sees half a file.
 */
final readonly class LocalFileStore implements FileStore
{
    private const string SAFE_PATH = '/\A[a-z_]+\/[0-9a-f]{2}\/[0-9a-f]{2}\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}(?:@[0-9]{1,4})?\.webp\z/';
    private const string TEMPORARY = 'tmp';

    private string $root;

    /** @param string $root absolute, or relative to the project directory */
    public function __construct(string $root, string $projectDir)
    {
        $this->root = rtrim(str_starts_with($root, '/') ? $root : $projectDir.'/'.$root, '/');
    }

    public function put(string $path, string $bytes): void
    {
        $target = $this->resolve($path);
        $temporary = $this->root.'/'.self::TEMPORARY.'/'.bin2hex(random_bytes(16));
        $this->directory(\dirname($target));
        $this->directory(\dirname($temporary));
        if (false === @file_put_contents($temporary, $bytes) || !@chmod($temporary, 0640) || !@rename($temporary, $target)) {
            @unlink($temporary);
            throw MediaException::storageUnavailable();
        }
    }

    public function read(string $path): string
    {
        $bytes = @file_get_contents($this->resolve($path));

        return false === $bytes ? throw MediaException::missing() : $bytes;
    }

    public function delete(string $path): void
    {
        $target = $this->resolve($path);
        if (is_file($target) && !@unlink($target)) {
            throw MediaException::storageUnavailable();
        }
    }

    public function all(): iterable
    {
        if (!is_dir($this->root)) {
            return;
        }
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = substr($file->getPathname(), \strlen($this->root) + 1);
            if (1 === preg_match(self::SAFE_PATH, $path)) {
                yield $path => $file->getMTime();
            }
        }
    }

    public function purgeTemporary(int $olderThan): int
    {
        $purged = 0;
        foreach (glob($this->root.'/'.self::TEMPORARY.'/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $olderThan && @unlink($file)) {
                ++$purged;
            }
        }

        return $purged;
    }

    /** Whether the root exists (creating it on first use) and can be written; used by the health check. */
    public function writable(): bool
    {
        return (is_dir($this->root) || @mkdir($this->root, 0750, true) || is_dir($this->root)) && is_writable($this->root);
    }

    private function resolve(string $path): string
    {
        if (1 !== preg_match(self::SAFE_PATH, $path)) {
            throw MediaException::missing();
        }

        return $this->root.'/'.$path;
    }

    private function directory(string $directory): void
    {
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw MediaException::storageUnavailable();
        }
    }
}
