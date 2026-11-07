<?php

declare(strict_types=1);

namespace App\ChildrenMinistry\Sessions\Application;

use App\ChildrenMinistry\Participation\Application\Contract\ParticipationReader;
use App\ChildrenMinistry\Safeguarding\Application\Contract\ChildReadiness;
use App\ChildrenMinistry\Safeguarding\Application\Contract\SafeguardingReader;
use App\ChildrenMinistry\Sessions\Application\Contract\MemberFamilyReader;
use App\ChildrenMinistry\Sessions\Application\Contract\SessionReader;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\ChildrenMinistry\Shared\Domain\ChildrenRules;
use App\People\Application\Contract\PersonDirectory;
use App\Shared\Application\ChurchCalendar;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * What a verified guardian may see about their own children. Every read re-checks live authority; a person id is never
 * an entitlement, and a child the guardian is not authorised for is reported as not found.
 */
final readonly class MemberFamilyViews
{
    private const int SESSION_HORIZON_SECONDS = 90 * 86400;

    public function __construct(
        private MemberFamilyReader $reader,
        private PersonDirectory $people,
        private ParticipationReader $participation,
        private ChildReadiness $readiness,
        private SessionChildPreparation $preparation,
        private SafeguardingReader $safeguarding,
        private SessionReader $sessions,
        private ChurchCalendar $calendar,
        private ClockInterface $clock,
    ) {
    }

    /** The guardian must be an available adult. */
    public function guardian(string $person): void
    {
        $entry = $this->people->get($person);
        if (null === $entry || !$entry->available) {
            throw ChildrenException::missing('Guardian');
        }
        ChildrenRules::adult($entry->birthDate, $this->calendar->today());
    }

    public function require(string $guardian, string $child): FamilyChild
    {
        $this->guardian($guardian);
        if (!$this->reader->authorized($guardian, $child, $this->clock->now())) {
            throw ChildrenException::missing('Authorized child');
        }
        $person = $this->people->get($child);
        if (null === $person || !$person->available) {
            throw ChildrenException::missing('Authorized child');
        }
        try {
            ChildrenRules::child($person->birthDate, $this->calendar->today());
        } catch (ChildrenException) {
            throw ChildrenException::missing('Authorized child');
        }

        return new FamilyChild($person->id, $person->name);
    }

    /** @return FamilyPage<FamilyChild> */
    public function children(string $guardian, int $limit, int $offset): FamilyPage
    {
        ChildrenRules::page($limit, $offset);
        $this->guardian($guardian);
        $ids = $this->reader->children($guardian, $this->clock->now(), $limit + 1, $offset);
        $today = $this->calendar->today();
        $children = [];
        foreach ($this->people->getAll(\array_slice($ids, 0, $limit)) as $person) {
            if ($person->available && null !== $person->birthDate && !$person->birthDate->isAfter($today) && $person->birthDate->plusYears(18)->isAfter($today)) {
                $children[] = new FamilyChild($person->id, $person->name);
            }
        }

        return new FamilyPage(true, $children, \count($ids) > $limit);
    }

    /** @return FamilyPage<FamilyProgramme> */
    public function programmes(string $guardian, string $child, int $limit, int $offset): FamilyPage
    {
        $this->require($guardian, $child);
        ChildrenRules::page($limit, $offset);
        $on = $this->calendar->today();
        $page = $this->reader->programmes($child, $on, $limit + 1, $offset);
        $items = [];
        foreach (\array_slice($page, 0, $limit) as $enrolment) {
            $detail = $this->readiness->details($enrolment->programmeId, [$child])[$child] ?? null;
            $className = null;
            foreach ($this->participation->classes($enrolment->programmeId) as $class) {
                if (null !== $this->participation->assignmentAt($class->id, $child, $on)) {
                    $className = $class->name;
                    break;
                }
            }
            $items[] = new FamilyProgramme($enrolment->programmeId, $enrolment->name, $enrolment->validFrom, $enrolment->validUntil, $className, $detail->steps ?? [], $detail->timeZone ?? $this->calendar->timeZone());
        }

        return new FamilyPage(true, $items, \count($page) > $limit);
    }

    /** @return FamilyPage<FamilySession> */
    public function sessions(string $guardian, string $child, int $limit, int $offset): FamilyPage
    {
        $this->require($guardian, $child);
        ChildrenRules::page($limit, $offset);
        $now = $this->clock->now();
        $page = $this->reader->sessions($child, $now, $now->modify('+'.self::SESSION_HORIZON_SECONDS.' seconds'), $limit + 1, $offset);
        $person = $this->people->get($child) ?? throw ChildrenException::missing('Authorized child');
        $items = [];
        foreach (\array_slice($page, 0, $limit) as $row) {
            $session = $row->session;
            try {
                $session->requireArrivalWindow($now);
                $open = true;
            } catch (ChildrenException) {
                $open = false;
            }
            $issues = $this->preparation->issues($session, [$child => $person], $now)[$child] ?? [];
            $items[] = new FamilySession($session->id, $row->programme, $row->className, $row->room, $row->location, $row->timeZone, $session->startsAt, $session->endsAt, $session->status->value, $issues, $open);
        }

        return new FamilyPage(true, $items, \count($page) > $limit);
    }

    /** @return FamilyPage<FamilyVisit> */
    public function attendance(string $guardian, string $child, int $limit, int $offset): FamilyPage
    {
        $this->require($guardian, $child);
        ChildrenRules::page($limit, $offset);
        $now = $this->clock->now();
        $page = $this->reader->attendance($guardian, $child, $now, $limit + 1, $offset);
        $items = [];
        foreach (\array_slice($page, 0, $limit) as $visit) {
            $session = $this->sessions->ministrySession($visit->sessionId) ?? throw ChildrenException::missing('Session');
            $programme = $this->participation->ministryClass($session->classId) ?? throw ChildrenException::missing('Class');
            $items[] = new FamilyVisit($visit, null === $visit->collectedAt && $this->canCollect($guardian, $child, $programme->programmeId, $now));
        }

        return new FamilyPage(true, $items, \count($page) > $limit);
    }

    /** Whether the guardian holds their own unrevoked pickup grant whose guardian authority is current. */
    public function canCollect(string $guardian, string $child, string $programme, DateTimeImmutable $now): bool
    {
        foreach ($this->safeguarding->validAuthorizations($programme, $child, $guardian, $now) as $grant) {
            $authority = $this->safeguarding->guardianAuthority($grant->authorityId);
            if (null !== $authority && null === $authority->revokedAt && ChildrenRules::covers($authority->validFrom, $authority->validUntil, $now, $now)) {
                return true;
            }
        }

        return false;
    }
}
