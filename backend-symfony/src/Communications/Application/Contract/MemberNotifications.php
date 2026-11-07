<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

/**
 * Trusted internal capability: places a generic notice in a member's inbox. Call it inside the originating command
 * transaction, so a rollback removes the notice. It does no delivery or external I/O.
 */
interface MemberNotifications
{
    /**
     * @param string|null $accountId the member's account; null (no account) publishes nothing
     * @param string      $sourceKey stable key of the source revision, so a retry is not a second notice
     */
    public function publish(?string $accountId, MemberNotificationKind $kind, string $sourceKey): void;
}
