<?php

declare(strict_types=1);

namespace App\Events\Application\Command;

use App\Events\Domain\Model\ChurchEvent;
use App\Events\Domain\Repository\EventRepository;
use App\Events\Domain\ValueObject\EventId;
use App\Shared\Application\WorkflowTransaction;
use OutOfBoundsException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class UpdateEventHandler
{
    public function __construct(private EventRepository $events, private WorkflowTransaction $transactions)
    {
    }

    #[AsMessageHandler]
    public function __invoke(UpdateEvent $command): void
    {
        $this->transactions->run(function () use ($command): void {
            $event = $this->find($command->id);
            $event->update($command->title, $command->type, $command->description);
            $this->events->save($event);
        });
    }

    private function find(string $id): ChurchEvent
    {
        return $this->events->find(EventId::fromString($id)) ?? throw new OutOfBoundsException('Event not found.');
    }
}
