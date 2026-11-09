<?php

declare(strict_types=1);

namespace App\Media\Application\Command\RemoveImage;

use App\Media\Application\ImageAccess;
use App\Media\Application\Port\MediaAuditWriter;
use App\Media\Application\Port\PendingDeletions;
use App\Media\Domain\Repository\ImageSlotRepository;
use App\Media\Domain\SlotKey;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class RemoveImageHandler implements CommandHandler
{
    public function __construct(
        private ImageAccess $access,
        private ImageSlotRepository $slots,
        private PendingDeletions $deletions,
        private MediaAuditWriter $audit,
        private ClockInterface $clock,
    ) {
    }

    public function handle(RemoveImageCommand $command): void
    {
        $key = SlotKey::of($command->kind, $command->ownerId);
        $this->access->manage($command->actorId, $key);

        $now = $this->clock->now();
        $slot = $this->slots->lock($key, $now);
        $previous = $slot->file;
        $this->slots->save($slot->clear($command->expectedVersion, $now));
        if (null !== $previous) {
            $this->deletions->queue($previous, $now);
        }
        $this->audit->record($key, 'IMAGE_REMOVED', sprintf('%s removed', $key->kind->value), $command->actorId, $now);
    }
}
