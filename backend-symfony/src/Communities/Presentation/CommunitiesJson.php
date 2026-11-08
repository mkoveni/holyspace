<?php

declare(strict_types=1);

namespace App\Communities\Presentation;

use App\Communities\Application\Contract\AppointmentView;
use App\Communities\Application\Contract\AttendanceReport;
use App\Communities\Application\Contract\CommunityView;
use App\Communities\Application\Contract\FollowUpView;
use App\Communities\Application\Contract\HistoryEntry;
use App\Communities\Application\Contract\LeadershipView;
use App\Communities\Application\Contract\MeetingPage;
use App\Communities\Application\Contract\MeetingSummary;
use App\Communities\Application\Contract\MeetingView;
use App\Communities\Application\Contract\MembershipView;
use App\Communities\Application\Contract\MemberView;
use App\Communities\Application\Contract\ParticipationEntry;
use App\Communities\Application\Contract\RosterEntryView;
use App\Communities\Application\Contract\RosterPage;
use App\Events\Application\Contract\CalendarOccurrence;
use App\Events\Application\Contract\CalendarPreview;
use App\Shared\Presentation\Json;
use DateTimeImmutable;

/** Java's record JSON for Communities: field names as in the records, nulls included, instants in UTC. */
final class CommunitiesJson
{
    private function __construct()
    {
    }

    /** @return array<string, mixed> */
    public static function community(CommunityView $c): array
    {
        return [
            'id' => $c->id,
            'kind' => $c->kind,
            'name' => $c->name,
            'description' => $c->description,
            'parentId' => $c->parentId,
            'campus' => $c->campus,
            'active' => $c->active,
            'version' => $c->version,
        ];
    }

    /** @return array<string, mixed> */
    public static function membership(MembershipView $m): array
    {
        return [
            'id' => $m->id,
            'communityId' => $m->communityId,
            'personId' => $m->personId,
            'startedAt' => Json::instant($m->startedAt),
            'endedAt' => self::instant($m->endedAt),
            'version' => $m->version,
        ];
    }

    /** @return array<string, mixed> */
    public static function member(MemberView $m): array
    {
        return ['membership' => self::membership($m->membership), 'name' => $m->name];
    }

    /** @return array<string, mixed> */
    public static function leadership(LeadershipView $l): array
    {
        return [
            'id' => $l->id,
            'membershipId' => $l->membershipId,
            'communityId' => $l->communityId,
            'accountId' => $l->accountId,
            'role' => $l->role,
            'startedAt' => Json::instant($l->startedAt),
            'endedAt' => self::instant($l->endedAt),
            'version' => $l->version,
        ];
    }

    /** @return array<string, mixed> */
    public static function appointment(AppointmentView $a): array
    {
        return ['leadership' => self::leadership($a->leadership), 'accountName' => $a->accountName, 'personName' => $a->personName];
    }

    /** @return array<string, mixed> */
    public static function history(HistoryEntry $h): array
    {
        return [
            'id' => $h->id,
            'targetId' => $h->targetId,
            'action' => $h->action,
            'reason' => $h->reason,
            'actorId' => $h->actorId,
            'occurredAt' => Json::instant($h->occurredAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function participation(ParticipationEntry $p): array
    {
        return [
            'communityId' => $p->communityId,
            'kind' => $p->kind,
            'name' => $p->name,
            'membershipId' => $p->membershipId,
            'startedAt' => Json::instant($p->startedAt),
            'endedAt' => self::instant($p->endedAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function followUp(FollowUpView $f): array
    {
        return [
            'id' => $f->id,
            'communityId' => $f->communityId,
            'personId' => $f->personId,
            'name' => $f->name,
            'note' => $f->note,
            'dueOn' => $f->dueOn->iso(),
            'status' => $f->status,
            'version' => $f->version,
        ];
    }

    /** @return array<string, mixed> Java `CommunityCalendar.Date` */
    public static function date(CalendarOccurrence $d): array
    {
        return [
            'id' => $d->id,
            'startsAt' => Json::instant($d->startsAt),
            'endsAt' => Json::instant($d->endsAt),
            'timeZone' => $d->timeZone,
            'venue' => $d->venue,
            'status' => $d->status,
            'revision' => $d->revision,
        ];
    }

    /** @return array<string, mixed> */
    public static function preview(CalendarPreview $p): array
    {
        return ['hash' => $p->hash, 'dates' => array_map(self::date(...), $p->dates)];
    }

    /** @return array<string, mixed> */
    public static function meeting(MeetingView $m): array
    {
        return [
            'id' => $m->id,
            'communityId' => $m->communityId,
            'occurrenceId' => $m->occurrenceId,
            'scheduledAt' => Json::instant($m->scheduledAt),
            'status' => $m->status,
            'rosterFrozen' => $m->rosterFrozen,
            'version' => $m->version,
        ];
    }

    /** @return array<string, mixed> */
    public static function counts(AttendanceReport $r): array
    {
        return [
            'meetingId' => $r->meetingId,
            'expected' => $r->expected,
            'present' => $r->present,
            'absent' => $r->absent,
            'excused' => $r->excused,
            'unrecorded' => $r->unrecorded,
            'guests' => $r->guests,
        ];
    }

    /** @return array<string, mixed> Java `CommunityViews.Meetings` */
    public static function meetings(MeetingPage $page): array
    {
        return [
            'meetings' => array_map(static fn (MeetingSummary $s): array => ['meeting' => self::meeting($s->meeting), 'date' => self::date($s->date), 'counts' => self::counts($s->counts)], $page->meetings),
            'total' => $page->total,
        ];
    }

    /** @return array<string, mixed> Java `CommunityViews.Roster` */
    public static function roster(RosterPage $page): array
    {
        return [
            'roster' => array_map(static fn (RosterEntryView $r): array => [
                'id' => $r->id,
                'meetingId' => $r->meetingId,
                'personId' => $r->personId,
                'membershipId' => $r->membershipId,
                'guest' => $r->guest,
                'status' => $r->status,
                'version' => $r->version,
                'name' => $r->name,
            ], $page->roster),
            'total' => $page->total,
            'counts' => self::counts($page->counts),
        ];
    }

    public static function instant(?DateTimeImmutable $at): ?string
    {
        return null === $at ? null : Json::instant($at);
    }

    /** Request-body UUIDs are compared and hashed as Java's lower-case `UUID.toString()`. */
    public static function uuid(?string $value): ?string
    {
        return null === $value ? null : strtolower($value);
    }
}
