<?php

declare(strict_types=1);

namespace App\People\Presentation\Http\Service;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class CommandDispatcher
{
    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    public function dispatch(object $command): mixed
    {
        return $this->bus->dispatch($command)->last(HandledStamp::class)?->getResult();
    }
}
