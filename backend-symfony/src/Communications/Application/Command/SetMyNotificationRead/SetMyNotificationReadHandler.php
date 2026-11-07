<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\SetMyNotificationRead;

use App\Communications\Application\Contract\InboxWriter;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Marks the caller's own notice read or unread. The account is rechecked after the write, inside the transaction. */
final readonly class SetMyNotificationReadHandler implements CommandHandler
{
    public function __construct(
        private AuthenticatedAccount $account,
        private InboxWriter $writer,
        private ClockInterface $clock,
    ) {
    }

    public function handle(SetMyNotificationReadCommand $command): void
    {
        $caller = $this->account->requireAccount();
        $this->writer->read($caller, $command->notificationId, $command->read, $this->clock->now());
        $this->account->requireAccount();
    }
}
