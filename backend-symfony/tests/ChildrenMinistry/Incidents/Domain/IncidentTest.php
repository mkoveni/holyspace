<?php

declare(strict_types=1);

namespace App\Tests\ChildrenMinistry\Incidents\Domain;

use App\ChildrenMinistry\Incidents\Domain\Incident;
use App\ChildrenMinistry\Incidents\Domain\IncidentDetails;
use App\ChildrenMinistry\Incidents\Domain\IncidentEvent;
use App\ChildrenMinistry\Incidents\Domain\IncidentEventType;
use App\ChildrenMinistry\Incidents\Domain\IncidentStatus;
use App\ChildrenMinistry\Shared\Domain\ChildrenException;
use App\Shared\Domain\CalendarDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class IncidentTest extends TestCase
{
    private const string OWNER = '00000000-0000-4000-8000-0000000000a1';

    private function report(string $category = 'INJURY', string $severity = 'HIGH', string $review = '2026-10-26', string $retain = '2027-10-26'): Incident
    {
        $now = new DateTimeImmutable('2026-10-26T08:00:00Z');

        return Incident::report('i', 'c', 's', 'g', $category, $severity, $now, 'r', $now, CalendarDate::parse($review), CalendarDate::parse($retain));
    }

    public function testAReportedIncidentIsOpenUnownedAndAtVersionZero(): void
    {
        $incident = $this->report();

        self::assertSame([IncidentStatus::Open, null, 0], [$incident->status, $incident->ownerId, $incident->version]);
    }

    public function testClosureNeedsAnOwnerAndReopeningPreservesIdentity(): void
    {
        $open = $this->report();
        $on = CalendarDate::parse('2026-10-26');
        try {
            $open->progress(0, IncidentEventType::Closed, $on);
            self::fail('Expected a conflict.');
        } catch (ChildrenException $conflict) {
            self::assertSame('Assign responsibility before closing this incident.', $conflict->getMessage());
        }

        $triaged = $open->triage(0, self::OWNER, $on->plusDays(1), $on);
        $closed = $triaged->progress(1, IncidentEventType::Closed, $on);
        try {
            $closed->progress(2, IncidentEventType::Action, $on);
            self::fail('Expected a conflict.');
        } catch (ChildrenException $conflict) {
            self::assertSame('Reopen this incident before recording further follow-up.', $conflict->getMessage());
        }
        $reopened = $closed->progress(2, IncidentEventType::Reopened, $on);

        self::assertSame(['i', IncidentStatus::InReview, 3, self::OWNER], [$reopened->id, $reopened->status, $reopened->version, $reopened->ownerId]);
        self::assertSame('2026-10-26', $reopened->reviewOn->iso(), 'reopening sets the review date to the day');
    }

    public function testOnlyClosedIncidentsReopenAndOnlyOpenOnesTakeActions(): void
    {
        $this->expectException(ChildrenException::class);
        $this->expectExceptionMessage('Only closed incidents can be reopened.');
        $this->report()->progress(0, IncidentEventType::Reopened, CalendarDate::parse('2026-10-26'));
    }

    public function testActionsAndContactsKeepTheStatusAndAdvanceTheVersion(): void
    {
        $on = CalendarDate::parse('2026-10-26');
        $triaged = $this->report()->triage(0, self::OWNER, $on, $on);

        $acted = $triaged->progress(1, IncidentEventType::GuardianContact, $on);

        self::assertSame([IncidentStatus::InReview, 2], [$acted->status, $acted->version]);
        self::assertSame(IncidentStatus::Open, $this->report()->progress(0, IncidentEventType::Action, $on)->status);
    }

    public function testTriageRefusesAPastReviewDateAMissingOwnerAndAStaleVersion(): void
    {
        $on = CalendarDate::parse('2026-10-26');
        foreach ([
            [0, null, $on],
            [0, self::OWNER, $on->plusDays(-1)],
            [0, self::OWNER, null],
            [1, self::OWNER, $on],
        ] as [$version, $owner, $review]) {
            try {
                $this->report()->triage($version, $owner, $review, $on);
                self::fail('Expected a refusal.');
            } catch (ChildrenException) {
                self::addToAssertionCount(1);
            }
        }
        $this->expectException(ChildrenException::class);
        $this->report()->triage(0, self::OWNER, $on->plusYears(2), $on);
    }

    public function testTriageAndProgressRefuseClosedIncidentsAndUnknownActions(): void
    {
        $on = CalendarDate::parse('2026-10-26');
        $closed = $this->report()->triage(0, self::OWNER, $on, $on)->progress(1, IncidentEventType::Closed, $on);

        try {
            $closed->triage(2, self::OWNER, $on, $on);
            self::fail('Expected a conflict.');
        } catch (ChildrenException $conflict) {
            self::assertStringContainsString('Reopen this incident', $conflict->getMessage());
        }
        $this->expectException(ChildrenException::class);
        $this->report()->progress(0, IncidentEventType::Triaged, $on);
    }

    public function testInvalidCategoriesSeveritiesAndDatesAreRejected(): void
    {
        foreach ([['WEATHER', 'LOW', '2026-10-26', '2027-10-26'], ['INJURY', 'SEVERE', '2026-10-26', '2027-10-26'], ['INJURY', 'LOW', '2026-10-26', '2026-10-25']] as [$category, $severity, $review, $retain]) {
            try {
                $this->report($category, $severity, $review, $retain);
                self::fail('Expected a rejection.');
            } catch (ChildrenException $invalid) {
                self::assertSame('Choose a valid incident category, severity, review date and retention date.', $invalid->getMessage());
            }
        }
    }

    public function testAnIncidentCannotBeReportedBeforeItHappened(): void
    {
        $now = new DateTimeImmutable('2026-10-26T08:00:00Z');

        $this->expectException(ChildrenException::class);
        Incident::report('i', 'c', 's', 'g', 'INJURY', 'LOW', $now->modify('+1 second'), 'r', $now, CalendarDate::parse('2026-10-26'), CalendarDate::parse('2026-10-27'));
    }

    public function testPrivatePayloadRepresentationsNeverIncludeTheirContent(): void
    {
        $details = new IncidentDetails('Sensitive report', 'Sensitive response');
        $event = new IncidentEvent('e', 'i', 0, IncidentEventType::Action, 'a', new DateTimeImmutable(), 'Sensitive conversation', null);

        self::assertStringNotContainsString('Sensitive', print_r($details, true));
        self::assertStringNotContainsString('Sensitive', print_r($event, true));
    }

    public function testDetailsMustBePresentAndBounded(): void
    {
        foreach ([['', 'x'], ['x', ''], [str_repeat('x', 5001), 'x'], ['x', str_repeat('x', 3001)], [null, 'x']] as [$description, $action]) {
            try {
                new IncidentDetails($description, $action);
                self::fail('Expected a rejection.');
            } catch (ChildrenException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
