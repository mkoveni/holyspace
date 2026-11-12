<?php

declare(strict_types=1);

namespace App\Events\Application;

use App\Communications\Application\Contract\DeliverySnapshot;
use App\Communications\Application\Contract\EmailDeliveries;
use App\Communications\Application\Contract\EmailTemplates;
use App\Events\Application\Contract\EventJournal;
use App\Events\Application\Contract\OccurrenceView;
use App\Events\Application\Contract\VisitMessagingReader;
use App\Events\Domain\EventRules;
use App\Events\Domain\Repository\VisitorMessageRepository;
use App\IdentityAccess\Application\Contract\ResponsibleAccountDirectory;
use App\Shared\Application\IdGenerator;
use App\Shared\Domain\ContextException;
use DateTimeImmutable;

/**
 * Queues the welcome and next-day follow-up for a visitor who gave an email address, when staff approved automatic
 * messages for the occurrence. The approving account is the delivery's approver, so Communications asks Events again
 * (and the approver must still hold the permission) before every send. A message that cannot be prepared is skipped:
 * it must never stop a visitor checking in.
 */
final readonly class VisitorMessenger
{
    public function __construct(
        private VisitMessagingReader $messaging,
        private ResponsibleAccountDirectory $accounts,
        private EmailTemplates $templates,
        private EmailDeliveries $emails,
        private VisitorMessageRepository $messages,
        private EventJournal $journal,
        private IdGenerator $ids,
    ) {
    }

    public function onArrival(string $registrationId, OccurrenceView $occurrence, string $eventTitle, string $firstName, ?string $email, DateTimeImmutable $now): void
    {
        $approver = $this->messaging->approver($occurrence->id);
        if (null === $approver || null === $email || !$this->accounts->eligible($approver, VisitorMessageRules::PERMISSION)) {
            return;
        }
        $zone = EventRules::zone($occurrence->timeZone);
        $values = [
            'name' => $firstName,
            'event' => $eventTitle,
            'startsAt' => $occurrence->startsAt->setTimezone($zone)->format('D, j M Y H:i'),
            'timeZone' => $occurrence->timeZone,
            'venue' => $occurrence->venueName,
        ];
        $this->queue(VisitorMessageRules::WELCOME, $registrationId, $approver, $email, $values, $now, $now);
        $this->queue(VisitorMessageRules::FOLLOW_UP, $registrationId, $approver, $email, $values, VisitorMessageRules::followUpAt($occurrence->endsAt, $zone), $now);
    }

    /** Emails a new member the invitation to the church app, once. A missing template or an earlier invitation means no new email. */
    public function inviteToApp(string $registrationId, string $approver, string $eventTitle, string $firstName, string $email, DateTimeImmutable $now): void
    {
        if ($this->messages->sent($registrationId, VisitorMessageRules::APP_INVITE)) {
            return;
        }
        $this->queue(VisitorMessageRules::APP_INVITE, $registrationId, $approver, $email, ['name' => $firstName, 'event' => $eventTitle], $now, $now);
    }

    /** @param array<string, string> $values */
    private function queue(string $purpose, string $registrationId, string $approver, string $email, array $values, DateTimeImmutable $sendAt, DateTimeImmutable $now): void
    {
        try {
            $content = $this->templates->render(VisitorMessageRules::template($purpose), $values);
        } catch (ContextException) {
            return; // no published template: the visit still stands
        }
        $delivery = $this->ids->uuid();
        $hash = VisitorMessageRules::hash($registrationId, $purpose, $email, $content->subject, $content->html, $content->version);
        $this->messages->record($registrationId, $purpose, $delivery, $hash, $now);
        $this->emails->queue('events', new DeliverySnapshot(
            $delivery,
            $this->ids->uuid(),
            null,
            null,
            $email,
            $content->subject,
            $content->html,
            $hash,
            $approver,
            $now,
            $sendAt,
            'QUEUED',
            0,
            '<'.$delivery.'@onechurch.delivery>',
            null,
            null,
            null,
            '',
            false,
            0,
            'text/html',
            VisitorMessageRules::template($purpose),
            $content->version,
        ));
        $this->journal->audit($approver, 'visitor.message.queued', $delivery, strtolower($purpose), $now);
    }
}
