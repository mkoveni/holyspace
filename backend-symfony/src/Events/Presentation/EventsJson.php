<?php

declare(strict_types=1);

namespace App\Events\Presentation;

use App\Communications\Application\Contract\DeliveryPage;
use App\Communications\Application\Contract\DeliverySummary;
use App\Events\Application\Contract\AttendanceCounts;
use App\Events\Application\Contract\AttendanceHistoryEntry;
use App\Events\Application\Contract\AttendanceView;
use App\Events\Application\Contract\CalendarItem;
use App\Events\Application\Contract\ChangePreviewItem;
use App\Events\Application\Contract\CloseoutHistoryEntry;
use App\Events\Application\Contract\CloseoutView;
use App\Events\Application\Contract\EventView;
use App\Events\Application\Contract\MemberEventItem;
use App\Events\Application\Contract\MemberEventPage;
use App\Events\Application\Contract\MemberOffer;
use App\Events\Application\Contract\NoticeTotals;
use App\Events\Application\Contract\OccurrenceChangePreview;
use App\Events\Application\Contract\OccurrenceDetails;
use App\Events\Application\Contract\OccurrenceHistoryEntry;
use App\Events\Application\Contract\OccurrenceView;
use App\Events\Application\Contract\PersonRegistration;
use App\Events\Application\Contract\PolicyView;
use App\Events\Application\Contract\ReadinessCheck;
use App\Events\Application\Contract\ReadinessView;
use App\Events\Application\Contract\RegistrationCounts;
use App\Events\Application\Contract\RegistrationHistoryEntry;
use App\Events\Application\Contract\RegistrationPage;
use App\Events\Application\Contract\RegistrationView;
use App\Events\Application\Contract\ReportPage;
use App\Events\Application\Contract\ReportRow;
use App\Events\Application\Contract\ReviewedNotice;
use App\Events\Application\Contract\RosterEntry;
use App\Events\Application\Contract\RosterPage;
use App\Events\Application\Contract\SeriesView;
use App\Events\Application\Contract\VenueView;
use App\Events\Application\SchedulePreview;
use App\Events\Application\WalkIn;
use App\Events\Domain\ScheduleWindow;
use App\Shared\Presentation\Json;

/** Wire format of Events read models; field names match the Java API. */
final class EventsJson
{
    private function __construct()
    {
    }

    /** @return array<string, mixed> */
    public static function venue(VenueView $venue): array
    {
        return ['id' => $venue->id, 'name' => $venue->name, 'address' => $venue->address, 'exclusive' => $venue->exclusive, 'active' => $venue->active, 'version' => $venue->version];
    }

    /** @return array<string, mixed> */
    public static function event(EventView $event): array
    {
        return ['id' => $event->id, 'title' => $event->title, 'type' => $event->type, 'description' => $event->description, 'active' => $event->active, 'version' => $event->version];
    }

    /** @return array<string, mixed> */
    public static function series(SeriesView $series): array
    {
        return [
            'id' => $series->id,
            'eventId' => $series->eventId,
            'timeZone' => $series->timeZone,
            'frequency' => $series->frequency,
            'interval' => $series->interval,
            'count' => $series->count,
            'firstDate' => $series->firstDate,
            'startsAt' => $series->startsAt,
            'endsAt' => $series->endsAt,
            'endDayOffset' => $series->endDayOffset,
            'venueId' => $series->venueId,
            'version' => $series->version,
        ];
    }

    /** @return array<string, mixed> */
    public static function preview(SchedulePreview $preview): array
    {
        return [
            'hash' => $preview->hash,
            'venue' => self::venue($preview->venue),
            'dates' => array_map(static fn (ScheduleWindow $window): array => [
                'originDate' => $window->originDate->iso(),
                'startsAt' => Json::instant($window->startsAt),
                'endsAt' => Json::instant($window->endsAt),
            ], $preview->dates),
        ];
    }

    /** @return array<string, mixed> */
    public static function occurrence(OccurrenceView $o): array
    {
        return [
            'id' => $o->id,
            'eventId' => $o->eventId,
            'seriesId' => $o->seriesId,
            'originDate' => $o->originDate,
            'timeZone' => $o->timeZone,
            'startsAt' => Json::instant($o->startsAt),
            'endsAt' => Json::instant($o->endsAt),
            'venueId' => $o->venueId,
            'venueName' => $o->venueName,
            'venueAddress' => $o->venueAddress,
            'exclusive' => $o->exclusive,
            'status' => $o->status,
            'overridden' => $o->overridden,
            'version' => $o->version,
            'scheduleRevision' => $o->scheduleRevision,
        ];
    }

    /** @return array<string, int> */
    public static function counts(RegistrationCounts $counts): array
    {
        return ['confirmed' => $counts->confirmed, 'waitlisted' => $counts->waitlisted, 'cancelled' => $counts->cancelled];
    }

    /** @return array<string, mixed> */
    public static function policy(PolicyView $policy): array
    {
        return ['occurrenceId' => $policy->occurrenceId, 'open' => $policy->open, 'capacity' => $policy->capacity, 'waitlistEnabled' => $policy->waitlistEnabled, 'nextPosition' => $policy->nextPosition, 'version' => $policy->version];
    }

    /** @return array<string, mixed> */
    public static function calendarItem(CalendarItem $item): array
    {
        return ['occurrence' => self::occurrence($item->occurrence), 'title' => $item->title, 'type' => $item->type, 'registrations' => self::counts($item->registrations)];
    }

    /** @return array<string, mixed> */
    public static function occurrenceDetails(OccurrenceDetails $d): array
    {
        return [
            'occurrence' => self::occurrence($d->occurrence),
            'title' => $d->title,
            'type' => $d->type,
            'series' => self::series($d->series),
            'registrationPolicy' => self::policy($d->registrationPolicy),
            'registrations' => self::counts($d->registrations),
        ];
    }

    /** @return array<string, mixed> */
    public static function changePreview(OccurrenceChangePreview $preview): array
    {
        return [
            'hash' => $preview->hash,
            'changes' => array_map(static fn (ChangePreviewItem $c): array => [
                'before' => self::occurrence($c->before),
                'after' => self::occurrence($c->after),
                'registrations' => self::counts($c->registrations),
                'registrationVersion' => $c->registrationVersion,
                'attended' => $c->attended,
            ], $preview->changes),
            'skipped' => $preview->skipped,
            'confirmedAffected' => $preview->confirmedAffected,
            'waitlistedAffected' => $preview->waitlistedAffected,
            'attendedAffected' => $preview->attendedAffected,
        ];
    }

    /** @return array<string, mixed> */
    public static function occurrenceEntry(OccurrenceHistoryEntry $e): array
    {
        return [
            'id' => $e->id,
            'action' => $e->action,
            'reason' => $e->reason,
            'startsAt' => Json::instant($e->startsAt),
            'endsAt' => Json::instant($e->endsAt),
            'venueId' => $e->venueId,
            'venueName' => $e->venueName,
            'venueAddress' => $e->venueAddress,
            'status' => $e->status,
            'version' => $e->version,
            'scheduleRevision' => $e->scheduleRevision,
            'occurredAt' => Json::instant($e->occurredAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function registration(RegistrationView $r): array
    {
        return [
            'id' => $r->id,
            'occurrenceId' => $r->occurrenceId,
            'personId' => $r->personId,
            'status' => $r->status,
            'position' => $r->position,
            'version' => $r->version,
            'createdAt' => Json::instant($r->createdAt),
            'updatedAt' => Json::instant($r->updatedAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function registrationPage(RegistrationPage $page): array
    {
        return [
            'registrations' => array_map(static fn (PersonRegistration $p): array => ['registration' => self::registration($p->registration), 'name' => $p->name], $page->registrations),
            'total' => $page->total,
            'counts' => self::counts($page->counts),
        ];
    }

    /** @return array<string, mixed> */
    public static function registrationEntry(RegistrationHistoryEntry $e): array
    {
        return ['id' => $e->id, 'action' => $e->action, 'reason' => $e->reason, 'occurredAt' => Json::instant($e->occurredAt)];
    }

    /** @return array<string, int> */
    public static function attendanceCounts(AttendanceCounts $c): array
    {
        return ['confirmed' => $c->confirmed, 'waitlisted' => $c->waitlisted, 'cancelled' => $c->cancelled, 'attended' => $c->attended, 'absent' => $c->absent];
    }

    /** @return array<string, mixed> */
    public static function attendance(AttendanceView $a): array
    {
        return ['id' => $a->id, 'registrationId' => $a->registrationId, 'arrivedAt' => Json::instant($a->arrivedAt), 'status' => $a->status, 'version' => $a->version];
    }

    /** @return array<string, mixed> */
    public static function roster(RosterPage $page): array
    {
        return [
            'attendees' => array_map(static fn (RosterEntry $e): array => [
                'registration' => self::registration($e->registration),
                'attendance' => null === $e->attendance ? null : self::attendance($e->attendance),
                'name' => $e->name,
                'status' => $e->status,
            ], $page->attendees),
            'total' => $page->total,
            'counts' => self::attendanceCounts($page->counts),
            'admissionOpen' => $page->admissionOpen,
        ];
    }

    /** @return array<string, mixed> */
    public static function attendanceEntry(AttendanceHistoryEntry $e): array
    {
        return ['id' => $e->id, 'actorId' => $e->actorId, 'action' => $e->action, 'reason' => $e->reason, 'occurredAt' => Json::instant($e->occurredAt)];
    }

    /** @return array<string, mixed> */
    public static function walkIn(WalkIn $w): array
    {
        return ['registrationId' => $w->registrationId, 'admitted' => $w->admitted, 'status' => $w->status];
    }

    /** @return array<string, mixed> */
    public static function report(ReportPage $page): array
    {
        return [
            'occurrences' => array_map(static fn (ReportRow $r): array => [
                'occurrenceId' => $r->occurrenceId,
                'eventId' => $r->eventId,
                'title' => $r->title,
                'startsAt' => Json::instant($r->startsAt),
                'endsAt' => Json::instant($r->endsAt),
                'timeZone' => $r->timeZone,
                'venue' => $r->venue,
                'status' => $r->status,
                'counts' => self::attendanceCounts($r->counts),
            ], $page->occurrences),
            'totals' => [
                'occurrences' => $page->totals->occurrences,
                'confirmed' => $page->totals->confirmed,
                'waitlisted' => $page->totals->waitlisted,
                'cancelled' => $page->totals->cancelled,
                'attended' => $page->totals->attended,
                'absent' => $page->totals->absent,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function closeout(CloseoutView $c): array
    {
        return [
            'occurrenceId' => $c->occurrenceId,
            'status' => $c->status,
            'version' => $c->version,
            'note' => $c->note,
            'followUp' => $c->followUp,
            'reviewHash' => $c->reviewHash,
            'updatedBy' => $c->updatedBy,
            'updatedAt' => null === $c->updatedAt ? null : Json::instant($c->updatedAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function notices(NoticeTotals $n): array
    {
        return [
            'approved' => $n->approved,
            'submitted' => $n->submitted,
            'delivered' => $n->delivered,
            'pending' => $n->pending,
            'failed' => $n->failed,
            'captured' => $n->captured,
            'cancelled' => $n->cancelled,
            'remindersApproved' => $n->remindersApproved,
            'complete' => $n->complete,
        ];
    }

    /** @return array<string, mixed> */
    public static function readiness(ReadinessView $v): array
    {
        return [
            'checks' => array_map(static fn (ReadinessCheck $c): array => ['key' => $c->key, 'title' => $c->title, 'status' => $c->status, 'detail' => $c->detail], $v->checks),
            'staffing' => [
                'shifts' => $v->staffing->shifts,
                'positions' => $v->staffing->positions,
                'confirmed' => $v->staffing->confirmed,
                'ready' => $v->staffing->ready,
                'proposed' => $v->staffing->proposed,
                'gaps' => $v->staffing->gaps,
                'complete' => $v->staffing->complete,
            ],
            'review' => [
                'occurrence' => self::occurrence($v->review->occurrence),
                'attendance' => self::attendanceCounts($v->review->attendance),
                'notices' => self::notices($v->review->notices),
                'hash' => $v->review->hash,
                'canClose' => $v->review->canClose,
            ],
            'closeout' => self::closeout($v->closeout),
            'changedSinceCloseout' => $v->changedSinceCloseout,
            'assessedAt' => Json::instant($v->assessedAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function closeoutEntry(CloseoutHistoryEntry $e): array
    {
        return [
            'id' => $e->id,
            'version' => $e->version,
            'action' => $e->action,
            'note' => $e->note,
            'followUp' => $e->followUp,
            'attended' => $e->attended,
            'absent' => $e->absent,
            'waitlisted' => $e->waitlisted,
            'unresolvedNotices' => $e->unresolvedNotices,
            'actorId' => $e->actorId,
            'actorName' => $e->actorName,
            'occurredAt' => Json::instant($e->occurredAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function notice(ReviewedNotice $n): array
    {
        return [
            'registrationId' => $n->registrationId,
            'registrationVersion' => $n->registrationVersion,
            'scheduleRevision' => $n->scheduleRevision,
            'eventVersion' => $n->eventVersion,
            'purpose' => $n->purpose,
            'contactId' => $n->contactId,
            'recipient' => $n->recipient,
            'subject' => $n->subject,
            'html' => $n->html,
            'hash' => $n->hash,
            'templateVersion' => $n->templateVersion,
        ];
    }

    /** @return array<string, mixed> */
    public static function deliveries(DeliveryPage $page): array
    {
        return [
            'deliveries' => array_map(static fn (DeliverySummary $d): array => [
                'id' => $d->id,
                'communicationId' => $d->communicationId,
                'status' => $d->status,
                'scheduledAt' => Json::instant($d->scheduledAt),
                'attempts' => $d->attempts,
                'errorCode' => $d->errorCode,
                'captured' => $d->captured,
                'version' => $d->version,
            ], $page->deliveries),
            'total' => $page->total,
            'failed' => $page->failed,
            'waiting' => $page->waiting,
        ];
    }

    /** @return array<string, mixed> */
    public static function memberItem(MemberEventItem $i): array
    {
        return [
            'id' => $i->id,
            'eventId' => $i->eventId,
            'title' => $i->title,
            'description' => $i->description,
            'startsAt' => Json::instant($i->startsAt),
            'endsAt' => Json::instant($i->endsAt),
            'timeZone' => $i->timeZone,
            'venueName' => $i->venueName,
            'venueAddress' => $i->venueAddress,
            'status' => $i->status,
            'registrationOpen' => $i->registrationOpen,
            'waitlistEnabled' => $i->waitlistEnabled,
            'remainingPlaces' => $i->remainingPlaces,
            'booking' => null === $i->booking ? null : ['id' => $i->booking->id, 'status' => $i->booking->status, 'version' => $i->booking->version, 'attendance' => $i->booking->attendance],
        ];
    }

    /** @return array<string, mixed> */
    public static function memberPage(MemberEventPage $page): array
    {
        return ['verified' => $page->verified, 'items' => array_map(self::memberItem(...), $page->items), 'hasMore' => $page->hasMore];
    }

    /** @return array<string, mixed> */
    public static function offer(MemberOffer $offer): array
    {
        return ['enabled' => $offer->enabled, 'version' => $offer->version];
    }
}
