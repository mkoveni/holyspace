<?php

declare(strict_types=1);

namespace App\Communications\Application\Query\GetMyNotification;

use App\Communications\Application\Contract\InboxReader;
use App\Communications\Application\Contract\MemberNotification;
use App\Communications\Domain\CommunicationException;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;

/** Resolves the caller's own notice before a push is opened. It does not mark it read. */
final readonly class GetMyNotificationHandler
{
    public function __construct(private AuthenticatedAccount $account, private InboxReader $reader)
    {
    }

    public function handle(GetMyNotificationQuery $query): MemberNotification
    {
        $caller = $this->account->requireAccount();

        return $this->reader->notification($caller, $query->notificationId) ?? throw CommunicationException::missing('Notification');
    }
}
