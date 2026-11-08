<?php

declare(strict_types=1);

namespace App\Communities\Application\Contract;

use App\Shared\Domain\CoverSlot;

interface CommunityCoverReader
{
    /** The community's cover pointer, or the empty slot (version 0) when one was never set. */
    public function cover(string $communityId): CoverSlot;
}
