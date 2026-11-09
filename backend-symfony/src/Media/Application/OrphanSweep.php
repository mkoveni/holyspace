<?php

declare(strict_types=1);

namespace App\Media\Application;

use App\Media\Application\Port\FileStore;
use App\Media\Application\Port\MediaFileCatalog;
use Psr\Clock\ClockInterface;

/**
 * Removes files no `media_files` row records: what a crash between writing a file and committing its row leaves behind.
 * A file younger than a day is never touched, because its upload may still be committing.
 */
final readonly class OrphanSweep
{
    public const int GRACE_SECONDS = 86_400;
    public const int TEMPORARY_GRACE_SECONDS = 3_600;

    public function __construct(private FileStore $files, private MediaFileCatalog $catalog, private ClockInterface $clock)
    {
    }

    /** @return array{orphans: int, temporary: int} */
    public function run(): array
    {
        $now = $this->clock->now()->getTimestamp();
        $orphans = 0;
        foreach (iterator_to_array($this->files->all()) as $path => $modified) {
            if ($modified > $now - self::GRACE_SECONDS) {
                continue;
            }
            $main = preg_replace('/@\d+(\.webp)\z/', '$1', $path) ?? $path;
            if (!$this->catalog->isKnown($main)) {
                $this->files->delete($path);
                ++$orphans;
            }
        }

        return ['orphans' => $orphans, 'temporary' => $this->files->purgeTemporary($now - self::TEMPORARY_GRACE_SECONDS)];
    }
}
