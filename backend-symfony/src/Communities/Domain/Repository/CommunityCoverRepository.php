<?php

declare(strict_types=1);

namespace App\Communities\Domain\Repository;

use App\Shared\Domain\CoverSlot;
use DateTimeImmutable;

interface CommunityCoverRepository
{
    /** Points the community at its new cover (or none); the caller holds the community lock and checked the version. */
    public function save(string $communityId, CoverSlot $slot, DateTimeImmutable $at): void;
}
