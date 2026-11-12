<?php

declare(strict_types=1);

namespace App\Events\Application\Command\ConfigureVisitLink;

use App\Events\Application\Contract\EventJournal;
use App\Events\Domain\EventException;
use App\Events\Domain\Repository\OccurrenceRepository;
use App\Events\Domain\Repository\RegistrationRepository;
use App\Events\Domain\Repository\VisitLinkRepository;
use App\Events\Domain\Repository\VisitMessagingRepository;
use App\Events\Domain\VisitLink;
use App\IdentityAccess\Application\Contract\PermissionChecker;
use App\Shared\Application\CommandHandler;
use Psr\Clock\ClockInterface;

/** Switches an occurrence's visitor QR link on or off, or replaces its secret. Holds the occurrence lock. */
final readonly class ConfigureVisitLinkHandler implements CommandHandler
{
    public function __construct(
        private PermissionChecker $authorization,
        private OccurrenceRepository $occurrences,
        private RegistrationRepository $registrations,
        private VisitLinkRepository $links,
        private VisitMessagingRepository $messaging,
        private EventJournal $journal,
        private ClockInterface $clock,
    ) {
    }

    public function handle(ConfigureVisitLinkCommand $command): void
    {
        $this->authorization->requireActorPermission($command->actorId, 'events.attendance.manage');
        if (!\in_array($command->action, [ConfigureVisitLinkCommand::ENABLE, ConfigureVisitLinkCommand::DISABLE, ConfigureVisitLinkCommand::ROTATE, ConfigureVisitLinkCommand::MESSAGES_ON, ConfigureVisitLinkCommand::MESSAGES_OFF], true)) {
            throw EventException::invalid('Choose enable, disable, rotate, messages on or messages off.');
        }
        $occurrence = $this->occurrences->findForUpdate($command->occurrenceId) ?? throw EventException::missing('Occurrence');
        $now = $this->clock->now();
        if (ConfigureVisitLinkCommand::MESSAGES_OFF === $command->action) {
            $this->messaging->withdraw($occurrence->id);
            $this->journal->audit($command->actorId, 'visitor.messages.off', $occurrence->id, 'Automatic visitor messages turned off.', $now);

            return;
        }
        if (ConfigureVisitLinkCommand::MESSAGES_ON === $command->action) {
            // The approver stands behind every message sent: Communications checks they still hold this permission.
            $this->authorization->requireActorPermission($command->actorId, 'events.communications.send');
            if ('SCHEDULED' !== $occurrence->status || $occurrence->endsAt <= $now) {
                throw EventException::conflict('Automatic visitor messages need a scheduled occurrence that has not ended.');
            }
            $this->messaging->approve($occurrence->id, $command->actorId, $now);
            $this->journal->audit($command->actorId, 'visitor.messages.on', $occurrence->id, 'Automatic visitor messages approved.', $now);

            return;
        }
        $current = $this->links->findUnlocked($occurrence->id);
        if (ConfigureVisitLinkCommand::DISABLE !== $command->action
            && ('SCHEDULED' !== $occurrence->status || $occurrence->endsAt <= $now)) {
            throw EventException::conflict('A visitor check-in link needs a scheduled occurrence that has not ended.');
        }
        if (ConfigureVisitLinkCommand::DISABLE !== $command->action && !($this->registrations->findPolicy($occurrence->id)->open ?? false)) {
            throw EventException::conflict('Open registration for this occurrence before turning on visitor check-in: visitors are booked at the door like any walk-in.');
        }
        $next = match ($command->action) {
            ConfigureVisitLinkCommand::ENABLE => $current?->enabled() ?? VisitLink::enable($occurrence->id),
            ConfigureVisitLinkCommand::ROTATE => $current?->rotated() ?? VisitLink::enable($occurrence->id),
            default => $current?->disabled(),
        };
        if (null !== $next) {
            $this->links->save($next, $command->actorId, $now);
            $this->journal->audit($command->actorId, 'visitor.link.'.strtolower($command->action), $occurrence->id, 'Visitor check-in link changed.', $now);
        }
    }
}
