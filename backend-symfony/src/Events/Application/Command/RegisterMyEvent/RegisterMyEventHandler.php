<?php

declare(strict_types=1);

namespace App\Events\Application\Command\RegisterMyEvent;

use App\Events\Application\MemberEventActions;
use App\Shared\Application\CommandHandler;

final readonly class RegisterMyEventHandler implements CommandHandler
{
    public function __construct(private MemberEventActions $actions)
    {
    }

    /** @return string the booking id */
    public function handle(RegisterMyEventCommand $command): string
    {
        return $this->actions->register($command->actorId, $command->requestId, $command->occurrenceId, $command->expectedVersion);
    }
}
