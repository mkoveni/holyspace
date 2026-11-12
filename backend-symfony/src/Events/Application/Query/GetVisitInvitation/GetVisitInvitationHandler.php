<?php

declare(strict_types=1);

namespace App\Events\Application\Query\GetVisitInvitation;

use App\Events\Application\Contract\EventReader;
use App\Events\Application\Contract\OccurrenceReader;
use App\Events\Application\Contract\VisitInvitation;
use App\Events\Application\Contract\VisitLinkReader;
use App\Events\Application\Contract\VisitTokens;
use App\Events\Domain\AdmissionWindow;
use App\Events\Domain\EventException;
use Psr\Clock\ClockInterface;

/** Public. A token that is forged, switched off or replaced is indistinguishable from one that never existed. */
final readonly class GetVisitInvitationHandler
{
    public function __construct(
        private VisitTokens $tokens,
        private VisitLinkReader $links,
        private OccurrenceReader $occurrences,
        private EventReader $events,
        private ClockInterface $clock,
    ) {
    }

    public function handle(GetVisitInvitationQuery $query): VisitInvitation
    {
        $claim = $this->tokens->verify($query->token) ?? throw EventException::missing('Check-in link');
        $state = $this->links->state($claim->occurrenceId);
        if (null === $state || !$state['enabled'] || $state['generation'] !== $claim->generation) {
            throw EventException::missing('Check-in link');
        }
        $occurrence = $this->occurrences->find($claim->occurrenceId) ?? throw EventException::missing('Check-in link');
        $event = $this->events->find($occurrence->eventId);
        if (null === $event || !$event->active) {
            throw EventException::missing('Check-in link');
        }

        return new VisitInvitation(
            $event->title,
            $occurrence->venueName,
            $occurrence->startsAt,
            $occurrence->endsAt,
            $occurrence->timeZone,
            AdmissionWindow::isOpen($occurrence->status, $occurrence->startsAt, $occurrence->endsAt, $this->clock->now()),
        );
    }
}
