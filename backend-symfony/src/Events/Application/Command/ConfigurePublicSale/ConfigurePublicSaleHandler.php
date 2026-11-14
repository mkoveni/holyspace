<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ConfigurePublicSale;

use App\Events\Application\Contract\EventJournal;
use App\Events\Domain\EventException;
use App\Events\Domain\EventRules;
use App\Events\Domain\PublicSale;
use App\Events\Domain\Repository\TicketRepository;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class ConfigurePublicSaleHandler implements CommandHandler
{
    public function __construct(private PermissionChecker $authorization, private TicketRepository $tickets, private EventJournal $journal, private ClockInterface $clock)
    {
    }

    public function handle(ConfigurePublicSaleCommand $c): void
    {
        $this->authorization->requireActorPermission($c->actorId, 'events.tickets.manage');
        $occurrence = $this->tickets->lockOccurrence($c->occurrenceId) ?? throw EventException::missing('Occurrence');
        $now = $this->clock->now();
        $current = $this->tickets->sale($c->occurrenceId) ?? PublicSale::initial($c->occurrenceId);
        EventRules::version($current->version, $c->expectedVersion);
        if ($c->enabled && !$occurrence->sellable($now)) {
            throw EventException::conflict('Public sales need an active scheduled occurrence that has not ended.');
        }
        if ($c->enabled && [] === array_filter($this->tickets->types($c->occurrenceId), static fn ($t): bool => $t->active)) {
            throw EventException::conflict('Add at least one active ticket type before opening sales.');
        }
        $this->tickets->saveSale($current->configure($c->enabled, $c->opensAt, $c->closesAt, $c->maxPerOrder));
        $this->journal->audit($c->actorId, $c->enabled ? 'tickets.sales.opened' : 'tickets.sales.closed', $c->occurrenceId, 'Public ticket sales configured.', $now);
    }
}
