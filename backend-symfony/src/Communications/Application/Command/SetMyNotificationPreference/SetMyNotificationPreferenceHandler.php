<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\SetMyNotificationPreference;

use App\Communications\Application\Contract\InboxWriter;
use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\MemberNotice;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandHandler;

/** Turns one inbox topic on or off for future notices. Email consent and existing notices are unaffected. */
final readonly class SetMyNotificationPreferenceHandler implements CommandHandler
{
    public function __construct(private AuthenticatedAccount $account, private InboxWriter $writer)
    {
    }

    public function handle(SetMyNotificationPreferenceCommand $command): void
    {
        $caller = $this->account->requireAccount();
        if (!in_array($command->topic, MemberNotice::TOPICS, true) || $command->expectedVersion < 0) {
            throw CommunicationException::invalid('Choose a supported topic and current preference version.');
        }
        $this->writer->preference($caller, $command->topic, $command->enabled, $command->expectedVersion);
        $this->account->requireAccount();
    }
}
