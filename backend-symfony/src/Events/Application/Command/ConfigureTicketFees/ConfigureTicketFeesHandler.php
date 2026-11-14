<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ConfigureTicketFees;

use App\Events\Application\Contract\EventJournal;
use App\Events\Domain\EventRules;
use App\Events\Domain\Repository\TicketRepository;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** The processing-fee estimate is a versioned setting, not a constant (D6). Orders already placed keep the fee they were quoted. */
final readonly class ConfigureTicketFeesHandler implements CommandHandler
{
    public function __construct(private PermissionChecker $authorization, private TicketRepository $tickets, private EventJournal $journal, private ClockInterface $clock)
    {
    }

    public function handle(ConfigureTicketFeesCommand $c): void
    {
        $this->authorization->requireActorPermission($c->actorId, 'events.tickets.manage');
        $current = $this->tickets->fees();
        EventRules::version($current->version, $c->expectedVersion);
        $this->tickets->saveFees($current->change($c->feeRatePpm, $c->feeFixedMinor, $c->providerVatPpm, $c->holdMinutes));
        $this->journal->audit($c->actorId, 'tickets.fees.updated', '00000000-0000-0000-0000-000000000000', 'Ticket processing-fee estimate changed.', $this->clock->now());
    }
}
