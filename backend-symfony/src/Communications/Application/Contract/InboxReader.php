<?php

declare(strict_types=1);

namespace App\Communications\Application\Contract;

/** A member's own inbox. Every read is scoped to the given account. */
interface InboxReader
{
    public function list(string $account, bool $unreadOnly, int $limit, int $offset): MemberInbox;

    /** @return list<MemberNotificationPreference> one per topic, defaults enabled at version 0 */
    public function preferences(string $account): array;

    /** The account's own notice, including one hidden by a preference; null for another account's id. */
    public function notification(string $account, string $id): ?MemberNotification;
}
