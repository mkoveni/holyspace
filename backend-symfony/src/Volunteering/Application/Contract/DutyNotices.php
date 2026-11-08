<?php

declare(strict_types=1);

namespace App\Volunteering\Application\Contract;

/**
 * Tells a linked volunteer about their duties in their member inbox, inside the planning transaction. A volunteer
 * without an account gets nothing. Implemented over the Communications inbox (`InboxDutyNotices`).
 */
interface DutyNotices
{
    public function assigned(?string $accountId, string $sourceKey): void;

    public function changed(?string $accountId, string $sourceKey): void;

    public function invited(?string $accountId, string $sourceKey): void;
}
