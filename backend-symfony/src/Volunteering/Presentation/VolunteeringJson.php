<?php

declare(strict_types=1);

namespace App\Volunteering\Presentation;

use App\Communications\Application\Contract\DeliveryPage;
use App\Communications\Application\Contract\DeliverySummary;
use App\People\Application\Contract\PersonEmail;
use App\Shared\Presentation\Json;
use App\Volunteering\Application\Contract\AssignmentEvent;
use App\Volunteering\Application\Contract\AssignmentView;
use App\Volunteering\Application\Contract\AvailabilityExceptionRecord;
use App\Volunteering\Application\Contract\AvailabilityRuleRecord;
use App\Volunteering\Application\Contract\Booking;
use App\Volunteering\Application\Contract\CredentialTypeView;
use App\Volunteering\Application\Contract\InvitationDetails;
use App\Volunteering\Application\Contract\InvitationRecord;
use App\Volunteering\Application\Contract\MemberDuty;
use App\Volunteering\Application\Contract\MemberOverview;
use App\Volunteering\Application\Contract\ProfileCredential;
use App\Volunteering\Application\Contract\ReminderPreview;
use App\Volunteering\Application\Contract\ServingPeriod;
use App\Volunteering\Application\Contract\ServingRoleRecord;
use App\Volunteering\Application\Contract\ShiftDetails;
use App\Volunteering\Application\Contract\ShiftDraft;
use App\Volunteering\Application\Contract\ShiftPage;
use App\Volunteering\Application\Contract\ShiftView;
use App\Volunteering\Application\Contract\SourceActivity;
use App\Volunteering\Application\Contract\TeamDetails;
use App\Volunteering\Application\Contract\TeamPage;
use App\Volunteering\Application\Contract\TeamRecord;
use App\Volunteering\Application\Contract\VolunteerPage;
use App\Volunteering\Application\Contract\VolunteerProfile;
use App\Volunteering\Application\Contract\VolunteerSummary;
use App\Volunteering\Application\Contract\VolunteerView;
use App\Volunteering\Application\Readiness;
use App\Volunteering\Domain\AvailabilityException;
use App\Volunteering\Domain\AvailabilityRule;
use DateTimeImmutable;

/** Java's Volunteering records as JSON: record field names, nulls included. */
final class VolunteeringJson
{
    private function __construct()
    {
    }

    /** @return array<string, mixed> */
    public static function teams(TeamPage $page): array
    {
        return ['teams' => array_map(self::team(...), $page->teams), 'total' => $page->total];
    }

    /** @return array<string, mixed> */
    public static function details(TeamDetails $details): array
    {
        return [
            'team' => self::team($details->team),
            'roles' => array_map(self::role(...), $details->roles),
            'memberships' => array_map(static fn (ServingPeriod $p): array => self::period($p, 'teamId'), $details->memberships),
            'approvals' => array_map(static fn (ServingPeriod $p): array => self::period($p, 'roleId'), $details->approvals),
        ];
    }

    /** @return array<string, mixed> */
    private static function team(TeamRecord $team): array
    {
        return ['id' => $team->id, 'name' => $team->name, 'active' => $team->active, 'version' => $team->version];
    }

    /** @return array<string, mixed> */
    private static function role(ServingRoleRecord $role): array
    {
        return [
            'id' => $role->id,
            'teamId' => $role->teamId,
            'name' => $role->name,
            'childrenRole' => $role->childrenRole,
            'active' => $role->active,
            'requiredCredentials' => $role->requiredCredentials,
        ];
    }

    /** @return array<string, mixed> Java `TeamMembership` (scope teamId) or `RoleApproval` (scope roleId) */
    private static function period(ServingPeriod $period, string $scope): array
    {
        return [
            'id' => $period->id,
            $scope => $period->scopeId,
            'volunteerId' => $period->volunteerId,
            'validFrom' => Json::instant($period->validFrom),
            'validUntil' => Json::instant($period->validUntil),
            'revokedAt' => null === $period->revokedAt ? null : Json::instant($period->revokedAt),
        ];
    }

    /** @return array<string, mixed> */
    public static function volunteers(VolunteerPage $page): array
    {
        return ['volunteers' => array_map(self::person(...), $page->volunteers), 'total' => $page->total];
    }

    /** @return array<string, mixed> */
    public static function profile(VolunteerProfile $profile): array
    {
        return [
            'person' => self::person($profile->person),
            'credentials' => array_map(static fn (ProfileCredential $c): array => [
                'id' => $c->id,
                'typeId' => $c->typeId,
                'type' => $c->type,
                'validFrom' => Json::instant($c->validFrom),
                'validUntil' => Json::instant($c->validUntil),
                'revokedAt' => self::optional($c->revokedAt),
            ], $profile->credentials),
            'weekly' => array_map(static fn (AvailabilityRuleRecord $r): array => ['id' => $r->id, 'volunteerId' => $r->volunteerId, 'revision' => $r->revision, ...self::weekly($r->rule)], $profile->weekly),
            'exceptions' => array_map(static fn (AvailabilityExceptionRecord $e): array => ['id' => $e->id, 'volunteerId' => $e->volunteerId, 'revision' => $e->revision, ...self::window($e->window)], $profile->exceptions),
        ];
    }

    /** @return array<string, mixed> Java `CredentialType` */
    public static function credentialType(CredentialTypeView $type): array
    {
        return ['id' => $type->id, 'code' => $type->code, 'name' => $type->name];
    }

    /** @return array<string, mixed> */
    public static function shifts(ShiftPage $page): array
    {
        return ['shifts' => array_map(self::shiftDetails(...), $page->shifts), 'total' => $page->total];
    }

    /** @return array<string, mixed> Java `ServingViews.ShiftView` */
    public static function shiftDetails(ShiftDetails $details): array
    {
        return [
            'shift' => self::shift($details->shift),
            'team' => $details->team,
            'role' => $details->role,
            'assignments' => array_map(static fn (Booking $b): array => [
                'assignment' => self::assignment($b->assignment),
                'name' => $b->name,
                'readiness' => self::readiness($b->readiness),
                'invitations' => array_map(self::invitationDetails(...), $b->invitations),
            ], $details->assignments),
            'reserved' => $details->reserved,
            'confirmed' => $details->confirmed,
            'ready' => $details->ready,
            'vacancies' => $details->vacancies,
        ];
    }

    /** @return array<string, mixed> Java `VolunteerReadiness.Assessment` */
    public static function readiness(Readiness $readiness): array
    {
        return ['ready' => $readiness->ready, 'issues' => $readiness->issues];
    }

    /** @return array<string, mixed> Java `ServingViews.InvitationView` */
    public static function invitationDetails(InvitationDetails $details): array
    {
        return [
            'invitation' => self::invitation($details->invitation),
            'effectiveStatus' => $details->effectiveStatus,
            'team' => $details->team,
            'role' => $details->role,
            'startsAt' => Json::instant($details->startsAt),
            'endsAt' => Json::instant($details->endsAt),
            'readiness' => self::readiness($details->readiness),
        ];
    }

    /** @return array<string, mixed> Java `ActivitySource.Activity` */
    public static function activity(SourceActivity $activity): array
    {
        return [
            'id' => $activity->id,
            'label' => $activity->label,
            'startsAt' => Json::instant($activity->startsAt),
            'endsAt' => Json::instant($activity->endsAt),
            'active' => $activity->active,
            'scheduleRevision' => $activity->scheduleRevision,
            'venueLabel' => $activity->venueLabel,
        ];
    }

    /** @return array<string, mixed> Java `RosterReader.Event` */
    public static function event(AssignmentEvent $event): array
    {
        return ['id' => $event->id, 'action' => $event->action, 'reason' => $event->reason, 'occurredAt' => Json::instant($event->occurredAt)];
    }

    /** @return array<string, mixed> Java `RosterPlanning.Draft` */
    public static function draft(ShiftDraft $draft): array
    {
        return [
            'requestId' => $draft->requestId,
            'teamId' => $draft->teamId,
            'roleId' => $draft->roleId,
            'sourceKind' => $draft->sourceKind,
            'sourceId' => $draft->sourceId,
            'startsAt' => self::optional($draft->startsAt),
            'endsAt' => self::optional($draft->endsAt),
            'positions' => $draft->positions,
        ];
    }

    /** @return array<string, mixed> Java `MemberVolunteerViews.Overview` */
    public static function overview(MemberOverview $overview): array
    {
        return [
            'permitted' => $overview->permitted,
            'linked' => $overview->linked,
            'volunteerId' => $overview->volunteerId,
            'status' => $overview->status,
            'version' => $overview->version,
            'weekly' => array_map(self::weekly(...), $overview->weekly),
            'exceptions' => array_map(self::window(...), $overview->exceptions),
            'items' => array_map(static fn (MemberDuty $d): array => [
                'id' => $d->id,
                'status' => $d->status,
                'shiftStatus' => $d->shiftStatus,
                'team' => $d->team,
                'role' => $d->role,
                'activity' => $d->activity,
                'location' => $d->location,
                'startsAt' => Json::instant($d->startsAt),
                'endsAt' => Json::instant($d->endsAt),
                'readiness' => self::readiness($d->readiness),
                'invitation' => null === $d->invitation ? null : [
                    'id' => $d->invitation->id,
                    'version' => $d->invitation->version,
                    'status' => $d->invitation->status,
                    'expiresAt' => Json::instant($d->invitation->expiresAt),
                ],
            ], $overview->items),
            'hasMore' => $overview->hasMore,
        ];
    }

    /**
     * @param list<PersonEmail> $emails
     *
     * @return list<array<string, string>> Java `PersonEmailDirectory.Email`
     */
    public static function contacts(array $emails): array
    {
        return array_map(static fn (PersonEmail $e): array => ['id' => $e->id, 'value' => $e->value], $emails);
    }

    /** @return array<string, mixed> Java `ReminderReview.Review` */
    public static function preview(ReminderPreview $preview): array
    {
        return [
            'assignmentId' => $preview->assignmentId,
            'assignmentVersion' => $preview->assignmentVersion,
            'shiftRevision' => $preview->shiftRevision,
            'contactId' => $preview->contactId,
            'recipient' => $preview->recipient,
            'subject' => $preview->subject,
            'html' => $preview->html,
            'hash' => $preview->hash,
            'templateVersion' => $preview->templateVersion,
        ];
    }

    /** @return array<string, mixed> Java `DeliveryReader.Page`, without recipients or bodies */
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

    /** @return array<string, mixed> Java `ServingViews.Person` */
    private static function person(VolunteerSummary $person): array
    {
        return ['volunteer' => self::volunteer($person->volunteer), 'name' => $person->name];
    }

    /** @return array<string, mixed> Java `Volunteer` */
    private static function volunteer(VolunteerView $v): array
    {
        return [
            'id' => $v->id,
            'personId' => $v->personId,
            'accountId' => $v->accountId,
            'status' => $v->status,
            'availabilityRevision' => $v->availabilityRevision,
            'version' => $v->version,
            'createdAt' => self::optional($v->createdAt),
            'updatedAt' => self::optional($v->updatedAt),
        ];
    }

    /** @return array<string, mixed> Java `Shift` */
    private static function shift(ShiftView $s): array
    {
        return [
            'id' => $s->id,
            'requestId' => $s->requestId,
            'requestHash' => $s->requestHash,
            'teamId' => $s->teamId,
            'roleId' => $s->roleId,
            'sourceKind' => $s->sourceKind,
            'sourceId' => $s->sourceId,
            'sourceRevision' => $s->sourceRevision,
            'startsAt' => Json::instant($s->startsAt),
            'endsAt' => Json::instant($s->endsAt),
            'positions' => $s->positions,
            'status' => $s->status,
            'version' => $s->version,
            'scheduleRevision' => $s->scheduleRevision,
        ];
    }

    /** @return array<string, mixed> Java `Assignment` */
    private static function assignment(AssignmentView $a): array
    {
        return [
            'id' => $a->id,
            'requestId' => $a->requestId,
            'requestHash' => $a->requestHash,
            'shiftId' => $a->shiftId,
            'volunteerId' => $a->volunteerId,
            'status' => $a->status,
            'version' => $a->version,
            'createdBy' => $a->createdBy,
            'createdAt' => self::optional($a->createdAt),
        ];
    }

    /** @return array<string, mixed> Java `Invitation` */
    private static function invitation(InvitationRecord $i): array
    {
        return [
            'id' => $i->id,
            'assignmentId' => $i->assignmentId,
            'assignmentVersion' => $i->assignmentVersion,
            'shiftVersion' => $i->shiftVersion,
            'expiresAt' => Json::instant($i->expiresAt),
            'status' => $i->status,
            'version' => $i->version,
            'respondedAt' => self::optional($i->respondedAt),
        ];
    }

    /** @return array<string, mixed> Java `VolunteerProfiles.Weekly` */
    private static function weekly(AvailabilityRule $rule): array
    {
        return [
            'dayOfWeek' => $rule->dayOfWeek,
            'startsAt' => self::localTime($rule->startsAt),
            'endsAt' => self::localTime($rule->endsAt),
            'timeZone' => $rule->timeZone,
            'validFrom' => $rule->validFrom,
            'validUntil' => $rule->validUntil,
        ];
    }

    /** @return array<string, mixed> Java `VolunteerProfiles.ExceptionWindow` */
    private static function window(AvailabilityException $window): array
    {
        return ['startsAt' => Json::instant($window->startsAt), 'endsAt' => Json::instant($window->endsAt), 'available' => $window->available];
    }

    /** Java `LocalTime.toString`: seconds only when they are not zero. */
    private static function localTime(string $time): string
    {
        return str_ends_with($time, ':00') ? substr($time, 0, 5) : $time;
    }

    private static function optional(?DateTimeImmutable $time): ?string
    {
        return null === $time ? null : Json::instant($time);
    }

    /**
     * @param list<string|null> $ids
     *
     * @return list<string|null>
     */
    public static function uuids(array $ids): array
    {
        return array_map(static fn (?string $id): ?string => null === $id ? null : strtolower($id), $ids);
    }
}
