<?php

declare(strict_types=1);

namespace App\Events\Application\Command\SaveTicketType;

use App\Events\Application\Contract\EventJournal;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\Events\Domain\Repository\TicketRepository;
use App\Events\Domain\TicketType;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use App\Shared\Application\IdGenerator;
use Psr\Clock\ClockInterface;

/**
 * Creates or edits a ticket type. Existing order lines keep the price and name they were bought at, so an edit never
 * changes history; holds already placed keep their pinned price.
 */
final readonly class SaveTicketTypeHandler implements CommandHandler
{
    public function __construct(private PermissionChecker $authorization, private TicketRepository $tickets, private EventJournal $journal, private IdGenerator $ids, private ClockInterface $clock)
    {
    }

    public function handle(SaveTicketTypeCommand $c): string
    {
        $this->authorization->requireActorPermission($c->actorId, 'events.tickets.manage');
        $this->tickets->lockOccurrence($c->occurrenceId) ?? throw EventException::missing('Occurrence');
        $existing = null;
        foreach ($this->tickets->types($c->occurrenceId) as $type) {
            if ($type->id === $c->id) {
                $existing = $type;
            }
        }
        if (null !== $c->id && null === $existing) {
            throw EventException::missing('Ticket type');
        }
        if (null !== $existing) {
            EventRules::version($existing->version, $c->expectedVersion);
        }
        $saved = new TicketType($c->id ?? $this->ids->uuid(), $c->occurrenceId, trim($c->name), trim($c->description), $c->priceMinor, $c->quantityCap, $c->sortOrder, $c->active, null === $existing ? 0 : $existing->version + 1);
        $this->tickets->saveType($saved);
        $this->journal->audit($c->actorId, null === $existing ? 'tickets.type.created' : 'tickets.type.updated', $saved->id, 'Ticket type saved.', $this->clock->now());

        return $saved->id;
    }
}
