<?php

declare(strict_types=1);

namespace App\Media\Application\Command\ImportCover;

use App\Media\Application\Port\FileStore;
use App\Media\Application\Port\ImageProcessor;
use App\Media\Application\Port\LegacyCoverSource;
use App\Media\Domain\MediaException;
use App\Media\Domain\MediaFile;
use App\Media\Domain\Repository\ImageSlotRepository;
use App\Media\Domain\SlotKey;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * One-off, idempotent. The new file keeps the legacy image id, so the old public URL maps to the new one by id alone.
 * A slot that Media has ever used (a picture, or one staff removed) is never touched, so re-running the import cannot
 * bring a removed cover back. The slot lock serializes
 * this against a staff upload of the same cover.
 */
final readonly class ImportCoverHandler implements CommandHandler
{
    public function __construct(
        private LegacyCoverSource $legacy,
        private ImageSlotRepository $slots,
        private ImageProcessor $processor,
        private FileStore $files,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ImportCoverCommand $command): string
    {
        $key = SlotKey::of($command->kind, $command->ownerId);
        $now = $this->clock->now();
        $slot = $this->slots->lock($key, $now);
        if ($slot->version() > 0) {
            return 'skipped';
        }
        $bytes = $this->legacy->bytes($command->imageId) ?? throw MediaException::missing();

        $policy = $command->kind->policy();
        $processed = $this->processor->process($bytes, $policy);
        $file = MediaFile::create($key, $command->imageId, $processed->width, $processed->height, \strlen($processed->bytes), hash('sha256', $processed->bytes), $now);
        $this->files->put($file->path, $processed->bytes);
        foreach ($policy->variantWidths as $width) {
            $this->files->put($file->variantPath($width), $processed->variants[$width] ?? throw MediaException::invalid('The image could not be processed.'));
        }
        $this->slots->save($slot->replace($file, 0, $now));

        return 'imported';
    }
}
