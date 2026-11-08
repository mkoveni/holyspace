<?php

declare(strict_types=1);

namespace App\Communities\Domain\Repository;

use App\Communities\Domain\CommunityNotice;

/** Approved notices. Approval holds the person, then the community row, so a request id is decided once. */
interface NoticeRepository
{
    public function insert(CommunityNotice $notice): void;
}
