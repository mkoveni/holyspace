<?php

declare(strict_types=1);

namespace App\Events\Application\Command;

use App\Events\Domain\Model\ChurchEvent;
use App\Events\Domain\Repository\EventRepository;
use App\Events\Domain\ValueObject\EventId;
use App\Events\Domain\ValueObject\EventTime;
use App\Events\Domain\ValueObject\OccurrenceId;
use App\Shared\Application\WorkflowTransaction;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class UpdateOccurrenceHandler
{
    public function __construct(private EventRepository $events, private WorkflowTransaction $transactions)
    {
    }

    #[AsMessageHandler]
    public function __invoke(UpdateOccurrence $command): void
    {
        $this->transactions->run(function () use ($command): void {
            $event = $this->find($command->eventId);
            $occurrence = $event->occurrence(OccurrenceId::fromString($command->occurrenceId));
            if (null === $occurrence) {
                throw new OutOfBoundsException('Occurrence not found for this event.');
            }
            $occurrence->reschedule(EventTime::local($command->startsAt, $event->timezone()), EventTime::local($command->endsAt, $event->timezone()), $command->location);
            $occurrence->changeStatus($command->status);
            $this->events->save($event);
        });
    }

    private function find(string $id): ChurchEvent
    {
        return $this->events->find(EventId::fromString($id)) ?? throw new OutOfBoundsException('Event not found.');
    }
}
