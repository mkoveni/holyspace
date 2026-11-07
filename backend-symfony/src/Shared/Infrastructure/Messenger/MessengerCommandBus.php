<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use App\Shared\Application\Command;
use App\Shared\Application\CommandBus;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class MessengerCommandBus implements CommandBus
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function dispatch(Command $command): mixed
    {
        try {
            $envelope = $this->bus->dispatch($command);
        } catch (HandlerFailedException $failure) {
            // Messenger wraps what the handler threw; callers (and the API error listener) expect the original.
            throw array_values($failure->getWrappedExceptions())[0] ?? $failure;
        }

        return $envelope->last(HandledStamp::class)?->getResult();
    }
}
