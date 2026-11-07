<?php

declare(strict_types=1);

namespace App\Events\Application\Command\IssueMyEventPass;

use App\Events\Application\IssuedPass;
use App\Events\Application\MemberEventActions;
use App\Shared\Application\CommandHandler;

final readonly class IssueMyEventPassHandler implements CommandHandler
{
    public function __construct(private MemberEventActions $actions)
    {
    }

    public function handle(IssueMyEventPassCommand $command): IssuedPass
    {
        return $this->actions->issue($command->actorId, $command->occurrenceId);
    }
}
