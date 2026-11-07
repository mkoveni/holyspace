<?php

declare(strict_types=1);

namespace App\Events\Application\Command\CancelMyEvent;

use App\Events\Application\MemberEventActions;
use App\Shared\Application\CommandHandler;

final readonly class CancelMyEventHandler implements CommandHandler
{
    public function __construct(private MemberEventActions $actions)
    {
    }

    public function handle(CancelMyEventCommand $command): void
    {
        $this->actions->cancel($command->actorId, $command->occurrenceId, $command->expectedVersion);
    }
}
