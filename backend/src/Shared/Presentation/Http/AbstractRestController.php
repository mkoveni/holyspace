<?php

namespace App\Shared\Presentation\Http;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

abstract class AbstractRestController
{
    public function __construct(protected MessageBusInterface $bus) {}

    protected function result(object $message): mixed
    {
        $envelope = $this->bus->dispatch($message);
        return $envelope->last(HandledStamp::class)?->getResult();
    }
}
