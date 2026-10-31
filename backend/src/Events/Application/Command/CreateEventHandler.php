<?php

declare(strict_types=1);

namespace App\Events\Application\Command;

use App\Events\Domain\Model\ChurchEvent;
use App\Events\Domain\Repository\EventRepository;
use App\Events\Domain\ValueObject\EventId;
use App\Events\Domain\ValueObject\EventTime;
use App\Shared\Application\WorkflowTransaction;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class CreateEventHandler
{
    public function __construct(private EventRepository $events, private WorkflowTransaction $transactions)
    {
    }

    #[AsMessageHandler]
    public function __invoke(CreateEvent $command): EventId
    {
        return $this->transactions->run(function () use ($command): EventId {
            $event = ChurchEvent::create($command->title, $command->type, $command->description, $command->timezone, EventTime::local($command->startsAt, $command->timezone), EventTime::local($command->endsAt, $command->timezone), $command->location, $command->recurrence, $command->occurrenceCount);
            $this->events->save($event);

            return $event->id();
        });
    }
}
