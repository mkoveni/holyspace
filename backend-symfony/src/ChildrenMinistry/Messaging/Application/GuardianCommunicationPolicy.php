<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Messaging\Application;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Safeguarding\Application\Contract\SafeguardingReader;
use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Sessions\Domain\SessionStatus;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\ChurchCalendar;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use DateTimeZone;

/** What must hold before a guardian message is prepared, recorded or sent: live authority, a real child, a real session. */
final readonly class GuardianCommunicationPolicy
{
    public function __construct(
        private SafeguardingReader $safeguarding,
        private ParticipationReader $participation,
        private SessionReader $sessions,
        private PersonDirectory $people,
        private ChurchCalendar $calendar,
    ) {
    }

    /** @return string the guardian's person id */
    public function guardian(string $child, string $authorityId, DateTimeImmutable $now): string
    {
        $authority = $this->safeguarding->guardianAuthority($authorityId);
        if (null === $authority || $authority->childId !== $child || null !== $authority->revokedAt || !ChildrenRules::covers($authority->validFrom, $authority->validUntil, $now, $now)) {
            throw ChildrenException::conflict('Current verified guardian authority is required before preparing or recording contact.');
        }
        $person = $this->people->get($authority->guardianId);
        if (null === $person || !$person->available) {
            throw ChildrenException::conflict('The guardian is unavailable.');
        }
        ChildrenRules::adult($person->birthDate, $this->day($now));

        return $authority->guardianId;
    }

    public function programme(string $programmeId, string $child, string $type, ?string $sessionId, DateTimeImmutable $now): ProgrammeFacts
    {
        $programme = $this->participation->programme($programmeId) ?? throw ChildrenException::missing('Programme');
        $on = $this->day($now);
        $identity = $this->people->get($child);
        if (null === $identity || !$identity->available) {
            throw ChildrenException::conflict('The child is unavailable.');
        }
        ChildrenRules::child($identity->birthDate, $on);
        if ('CONSENT_RENEWAL' === $type) {
            if (null !== $sessionId || null === $programme->currentPolicyId) {
                throw ChildrenException::invalid('Publish a consent policy and omit a session for a consent-renewal request.');
            }
        } elseif ('SESSION_REMINDER' === $type) {
            $session = null === $sessionId ? null : $this->sessions->ministrySession($sessionId);
            if (null === $session || $session->startsAt <= $now || SessionStatus::Closed === $session->status || SessionStatus::Cancelled === $session->status) {
                throw ChildrenException::conflict('Choose an upcoming active session.');
            }
            $class = $this->participation->ministryClass($session->classId);
            if (null === $class || $class->programmeId !== $programmeId) {
                throw ChildrenException::invalid('The session belongs to another programme.');
            }
            $on = CalendarDate::fromInstant($session->startsAt, $this->zone());
            ChildrenRules::child($identity->birthDate, $on);
            $placement = $this->participation->assignmentAt($session->classId, $child, $on);
            $enrolment = null === $placement ? null : $this->participation->enrolment($placement->enrolmentId);
            if (null === $enrolment || !$enrolment->covers($on)) {
                throw ChildrenException::conflict('The child needs a current placement covering this session.');
            }
        } else {
            throw ChildrenException::invalid('Choose a communication type.');
        }
        if (null === $this->participation->enrolmentAt($programmeId, $child, $on)) {
            throw ChildrenException::conflict('An active programme enrolment is required.');
        }

        return new ProgrammeFacts($programme->name, $programme->currentPolicyId);
    }

    /** Safe wording: names, programme and dates only, never incident or care content. */
    public function message(string $child, string $guardian, ProgrammeFacts $programme, string $type, ?string $sessionId): string
    {
        $childName = ($this->people->get($child) ?? throw ChildrenException::missing('Child'))->name;
        $guardianName = ($this->people->get($guardian) ?? throw ChildrenException::missing('Guardian'))->name;
        if ('CONSENT_RENEWAL' === $type) {
            $label = ($this->safeguarding->consentPolicy((string) $programme->currentPolicyId) ?? throw ChildrenException::missing('Consent policy'))->label;

            return 'Hello '.$guardianName.', please contact the ministry team to review and renew consent for '.$childName.' in '.$programme->name.' (policy: '.$label.'). Consent must be recorded by an authorized coordinator before attendance. Thank you.';
        }
        $session = $this->sessions->ministrySession((string) $sessionId) ?? throw ChildrenException::missing('Session');

        return 'Hello '.$guardianName.', a reminder that '.$childName.' is scheduled for '.$programme->name.' on '.$this->when($session->startsAt).'. Please speak to the ministry team about preparation and collection arrangements. This reminder does not confirm admission or pickup authority. Thank you.';
    }

    /** @return array<string, string> every variable a children template may use */
    public function variables(CommunicationView $communication): array
    {
        $programme = $this->participation->programme($communication->programmeId) ?? throw ChildrenException::missing('Programme');
        $session = null === $communication->sessionId ? null : $this->sessions->ministrySession($communication->sessionId);

        return [
            'message' => $communication->message,
            'guardianName' => ($this->people->get($communication->guardianId) ?? throw ChildrenException::missing('Guardian'))->name,
            'childName' => ($this->people->get($communication->childId) ?? throw ChildrenException::missing('Child'))->name,
            'programmeName' => $programme->name,
            'sessionDate' => null === $session ? '' : $this->when($session->startsAt),
            'policyName' => null === $communication->policyId ? '' : ($this->safeguarding->consentPolicy($communication->policyId)->label ?? ''),
        ];
    }

    /** A reminder is due on or before the day of its session. */
    public function contactDate(?string $sessionId, CalendarDate $dueOn): void
    {
        $session = null === $sessionId ? null : $this->sessions->ministrySession($sessionId);
        if (null !== $session && $dueOn->isAfter(CalendarDate::fromInstant($session->startsAt, $this->zone()))) {
            throw ChildrenException::invalid('The reminder contact date must not follow the session date.');
        }
    }

    private function day(DateTimeImmutable $at): CalendarDate
    {
        return CalendarDate::fromInstant($at, $this->zone());
    }

    private function zone(): DateTimeZone
    {
        return new DateTimeZone($this->calendar->timeZone());
    }

    private function when(DateTimeImmutable $at): string
    {
        return $at->setTimezone($this->zone())->format('d M Y \a\t H:i').' '.$this->calendar->timeZone();
    }
}
