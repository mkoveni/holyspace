<?php

declare(strict_types=1);

namespace App\Communications\Application\Command\SetMyPushPreference;

use App\Communications\Application\Contract\PushWriter;
use App\Communications\Domain\CommunicationException;
use App\Communications\Domain\MemberNotice;
use App\IdentityAccess\Application\Contract\AuthenticatedAccount;
use App\Shared\Application\CommandHandler;

/** Opts one push topic in or out, independently of the inbox and email. */
final readonly class SetMyPushPreferenceHandler implements CommandHandler
{
    public function __construct(private AuthenticatedAccount $account, private PushWriter $writer)
    {
    }

    public function handle(SetMyPushPreferenceCommand $command): void
    {
        $caller = $this->account->requireAccount();
        if (!in_array($command->topic, MemberNotice::TOPICS, true) || $command->expectedVersion < 0) {
            throw CommunicationException::invalid('Choose a valid push topic and version.');
        }
        $this->writer->preference($caller, $command->topic, $command->enabled, $command->expectedVersion);
        $this->account->requireAccount();
    }
}
