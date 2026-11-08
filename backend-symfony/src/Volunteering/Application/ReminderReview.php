<?php

declare(strict_types=1);

namespace App\Volunteering\Application;

use App\Communications\Application\Contract\EmailTemplates;
use App\People\Application\Contract\PersonDirectory;
use App\People\Application\Contract\PersonEmail;
use App\People\Application\Contract\PersonEmailDirectory;
use App\Volunteering\Application\Contract\ReminderPreview;
use App\Volunteering\Application\Contract\RosterDirectory;
use App\Volunteering\Application\Contract\TeamDirectory;
use App\Volunteering\Application\Contract\VolunteerReader;
use App\Volunteering\Domain\ServingRules;
use App\Volunteering\Domain\VolunteerException;
use DateTimeImmutable;

/**
 * Java `ReminderReview`: renders the published shift reminder for a confirmed upcoming duty and one of the volunteer's
 * current People emails. The hash binds assignment version, schedule revision, contact, recipient and rendered content,
 * so approval and every dispatch can prove nothing changed since the review.
 */
final readonly class ReminderReview
{
    public const string TEMPLATE = 'volunteering.shift-reminder';

    public function __construct(
        private RosterDirectory $roster,
        private VolunteerReader $volunteers,
        private TeamDirectory $teams,
        private PersonDirectory $people,
        private PersonEmailDirectory $emails,
        private EmailTemplates $templates,
    ) {
    }

    /** @return list<PersonEmail> */
    public function contacts(string $assignmentId): array
    {
        $assignment = $this->roster->assignment($assignmentId) ?? throw VolunteerException::missing('Assignment');
        $volunteer = $this->volunteers->volunteer($assignment->volunteerId) ?? throw VolunteerException::missing('Volunteer');

        return $this->emails->active($volunteer->personId);
    }

    public function review(string $assignmentId, string $contactId, ?string $message, DateTimeImmutable $now): ReminderPreview
    {
        $note = ServingRules::text($message, 2000);
        $assignment = $this->roster->assignment($assignmentId) ?? throw VolunteerException::missing('Assignment');
        $shift = $this->roster->shift($assignment->shiftId) ?? throw VolunteerException::missing('Shift');
        $volunteer = $this->volunteers->volunteer($assignment->volunteerId) ?? throw VolunteerException::missing('Volunteer');
        $person = $this->people->get($volunteer->personId);
        if (null === $person || !$person->available) {
            throw VolunteerException::conflict('This person is unavailable.');
        }
        if ('ACTIVE' !== $volunteer->status || 'CONFIRMED' !== $assignment->status || 'OPEN' !== $shift->status || !($shift->startsAt > $now)) {
            throw VolunteerException::conflict('Reminders require an active volunteer with a confirmed upcoming shift.');
        }
        $recipient = null;
        foreach ($this->emails->active($volunteer->personId) as $email) {
            if ($email->id === $contactId) {
                $recipient = $email->value;
                break;
            }
        }
        if (null === $recipient) {
            throw VolunteerException::conflict('Choose a current email address.');
        }
        $content = $this->templates->render(self::TEMPLATE, [
            'name' => $person->name,
            'team' => ($this->teams->team($shift->teamId) ?? throw VolunteerException::missing('Team'))->name,
            'role' => ($this->teams->role($shift->roleId) ?? throw VolunteerException::missing('Role'))->name,
            'startsAt' => Fingerprint::instant($shift->startsAt),
            'endsAt' => Fingerprint::instant($shift->endsAt),
            'message' => $note,
        ]);

        return new ReminderPreview(
            $assignment->id,
            $assignment->version,
            $shift->scheduleRevision,
            $contactId,
            $recipient,
            $content->subject,
            $content->html,
            self::hash($assignment->id, $assignment->version, $shift->scheduleRevision, $contactId, $recipient, $content->subject, $content->html, $content->version),
            $content->version,
        );
    }

    public static function hash(string $assignmentId, int $assignmentVersion, int $scheduleRevision, ?string $contactId, string $recipient, string $subject, string $html, int $templateVersion): string
    {
        return Fingerprint::of($assignmentId, (string) $assignmentVersion, (string) $scheduleRevision, $contactId, $recipient, $subject, $html, (string) $templateVersion);
    }
}
