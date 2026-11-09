<?php

declare(strict_types=1);

namespace App\Media\Application\Command\ChangeImage;

use App\Media\Application\ImageAccess;
use App\Media\Application\Port\FileStore;
use App\Media\Application\Port\ImageProcessor;
use App\Media\Application\Port\MediaAuditWriter;
use App\Media\Application\Port\PendingDeletions;
use App\Media\Domain\ImagePolicy;
use App\Media\Domain\MediaException;
use App\Media\Domain\MediaFile;
use App\Media\Domain\Repository\ImageSlotRepository;
use App\Media\Domain\SlotKey;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * Order matters because the file system is not transactional: the owner decides access, the slot is locked and its
 * version checked, then the upload is processed and written under a fresh id (a crash leaves an orphan the garbage
 * collector removes), the pointer and audit row commit, and the replaced file is only queued for deletion.
 */
final readonly class ChangeImageHandler implements CommandHandler
{
    public function __construct(
        private ImageAccess $access,
        private ImageSlotRepository $slots,
        private ImageProcessor $processor,
        private FileStore $files,
        private PendingDeletions $deletions,
        private MediaAuditWriter $audit,
        private IdGenerator $ids,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ChangeImageCommand $command): void
    {
        $key = SlotKey::of($command->kind, $command->ownerId);
        $this->access->manage($command->actorId, $key);
        if ('' === $command->bytes) {
            throw MediaException::invalid('Choose an image file.');
        }
        if (\strlen($command->bytes) > ImagePolicy::MAX_SOURCE_BYTES) {
            throw MediaException::invalid('Choose an image of at most 5 MB.');
        }

        $now = $this->clock->now();
        $slot = $this->slots->lock($key, $now);
        $slot->assertVersion($command->expectedVersion);

        $policy = $command->kind->policy();
        $processed = $this->processor->process($command->bytes, $policy);
        $file = MediaFile::create($key, $this->ids->uuid(), $processed->width, $processed->height, \strlen($processed->bytes), hash('sha256', $processed->bytes), $now);
        $this->files->put($file->path, $processed->bytes);
        foreach ($policy->variantWidths as $width) {
            $this->files->put($file->variantPath($width), $processed->variants[$width] ?? throw MediaException::invalid('The image could not be processed.'));
        }

        $previous = $slot->file;
        $this->slots->save($slot->replace($file, $command->expectedVersion, $now));
        if (null !== $previous) {
            $this->deletions->queue($previous, $now);
        }
        $this->audit->record($key, 'IMAGE_CHANGED', sprintf('%s set, %d bytes, %dx%d', $key->kind->value, $file->byteSize, $file->width, $file->height), $command->actorId, $now);
    }
}
