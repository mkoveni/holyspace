<?php

declare(strict_types=1);

namespace App\Media\Domain\Repository;

use App\Media\Domain\ImageSlot;
use App\Media\Domain\SlotKey;
use DateTimeImmutable;

interface ImageSlotRepository
{
    /**
     * The slot under a row lock held until the transaction ends. A slot that was never used comes back as version 0
     * with no file; the lock still serializes the first two uploads racing for it.
     */
    public function lock(SlotKey $key, DateTimeImmutable $now): ImageSlot;

    /** Writes the slot's file (if new) and pointer; fails if the stored version is not the one this slot advanced from. */
    public function save(ImageSlot $slot): void;
}
