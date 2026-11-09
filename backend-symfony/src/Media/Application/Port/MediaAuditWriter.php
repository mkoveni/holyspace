<?php

declare(strict_types=1);

namespace App\Media\Application\Port;

use App\Media\Domain\SlotKey;
use DateTimeImmutable;

/** Appends to `media_audit` inside the command's transaction. Metadata only: never bytes, names or EXIF data. */
interface MediaAuditWriter
{
    public function record(SlotKey $key, string $action, string $detail, string $actorId, DateTimeImmutable $at): void;
}
