<?php

declare(strict_types=1);

namespace App\Communications\Application\Query\ListMyNotifications;

use App\Communications\Application\Contract\InboxReader;
use App\Communications\Application\Contract\MemberInbox;
use App\Communications\Domain\CommunicationException;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;

final readonly class ListMyNotificationsHandler
{
    public function __construct(private AuthenticatedAccount $account, private InboxReader $reader)
    {
    }

    public function handle(ListMyNotificationsQuery $query): MemberInbox
    {
        $caller = $this->account->requireAccount();
        if ($query->limit < 1 || $query->limit > 100 || $query->offset < 0 || $query->offset > 10000) {
            throw CommunicationException::invalid('Choose a page of 1–100 notifications within the first 10000 results.');
        }

        return $this->reader->list($caller, $query->unreadOnly, $query->limit, $query->offset);
    }
}
