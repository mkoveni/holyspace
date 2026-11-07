<?php

declare(strict_types=1);

namespace App\Communications\Application\Query\GetMyNotificationPreferences;

use App\Communications\Application\Contract\InboxReader;
use App\Communications\Application\Contract\MemberNotificationPreference;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;

final readonly class GetMyNotificationPreferencesHandler
{
    public function __construct(private AuthenticatedAccount $account, private InboxReader $reader)
    {
    }

    /** @return list<MemberNotificationPreference> */
    public function handle(GetMyNotificationPreferencesQuery $query): array
    {
        return $this->reader->preferences($this->account->requireAccount());
    }
}
