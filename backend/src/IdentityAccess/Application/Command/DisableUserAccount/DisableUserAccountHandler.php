<?php

declare(strict_types=1);

namespace App\IdentityAccess\Application\Command\DisableUserAccount;

use App\IdentityAccess\Application\Command\DisableAccountAndSessions\DisableAccountAndSessionsCommand;
use App\IdentityAccess\Application\Command\DisableAccountAndSessions\DisableAccountAndSessionsHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DisableUserAccountHandler
{
    public function __construct(private DisableAccountAndSessionsHandler $disableAccountAndSessionsHandler)
    {
    }

    public function __invoke(DisableUserAccountCommand $command): void
    {
        ($this->disableAccountAndSessionsHandler)(new DisableAccountAndSessionsCommand($command->userId));
    }
}
