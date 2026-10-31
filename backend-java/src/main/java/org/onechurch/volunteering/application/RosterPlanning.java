package org.onechurch.volunteering.application;

import org.onechurch.communications.application.contract.MemberNotifications;
import org.onechurch.shared.application.UnitOfWork;
import org.onechurch.volunteering.application.contract.RosterReader;
import org.onechurch.volunteering.application.contract.ServingActivityLocks;
import org.onechurch.volunteering.application.contract.ServingAudit;
import org.onechurch.volunteering.application.contract.TeamReader;
import org.onechurch.volunteering.application.contract.VolunteerReader;
import org.onechurch.volunteering.domain.Assignment;
import org.onechurch.volunteering.domain.Invitation;
import org.onechurch.volunteering.domain.ServingRole;
import org.onechurch.volunteering.domain.ServingRules;
import org.onechurch.volunteering.domain.Shift;
import org.onechurch.volunteering.domain.Team;
import org.onechurch.volunteering.domain.Volunteer;
import org.onechurch.volunteering.domain.VolunteerException;
import org.onechurch.volunteering.domain.repository.RosterRepository;
import org.onechurch.volunteering.domain.repository.TeamRepository;

import java.time.Clock;
import java.time.Instant;
import java.util.Comparator;
import java.util.List;
import java.util.Objects;
import java.util.Optional;
import java.util.UUID;

public final class RosterPlanning {
    public record Draft(UUID requestId, UUID teamId, UUID roleId, String sourceKind, UUID sourceId, Instant startsAt,
        Instant endsAt, int positions) {}
    private final RosterRepository repository;
    private final RosterReader roster;
    private final TeamReader teams;
    private final TeamRepository teamRepository;
    private final VolunteerProfiles profiles;
    private final VolunteerReader volunteers;
    private final ActivityRegistry activities;
    private final UnitOfWork tx;
    private final ServingAudit audit;
    private final Clock clock;
    private final MemberNotifications notifications;
    public RosterPlanning(RosterRepository repository, RosterReader roster, TeamReader teams,
        TeamRepository teamRepository, VolunteerProfiles profiles, VolunteerReader volunteers,
        ActivityRegistry activities, UnitOfWork tx, ServingAudit audit, Clock clock,
        MemberNotifications notifications) {
        this.repository = repository;
        this.roster = roster;
        this.teams = teams;
        this.teamRepository = teamRepository;
        this.profiles = profiles;
        this.volunteers = volunteers;
        this.activities = activities;
        this.tx = tx;
        this.audit = audit;
        this.clock = clock;
        this.notifications = notifications;
    }
    private void notifyVolunteer(UUID volunteer, MemberNotifications.Kind kind, String source) {
        volunteers.volunteer(volunteer)
            .map(Volunteer::accountId)
            .ifPresent(account -> notifications.publish(account, kind, source));
    }
    public UUID create(UUID actor, Draft d) {
        return tx.execute(() -> {
            if (d != null) {
                validateSource(d);
            }
            if (d != null && d.sourceId() != null) {
                profiles.lockAll(List.of(), List.of(new ServingActivityLocks.Activity(d.sourceKind(), d.sourceId())));
            }
            return createLocked(actor, d);
        });
    }
    private static void validateSource(Draft draft) {
        ServingRules.id(draft.requestId());
        ServingRules.id(draft.teamId());
        ServingRules.id(draft.roleId());
        if (draft.sourceKind() == null || draft.sourceKind().length() > 40
            || (draft.sourceKind().equals("STANDALONE") != (draft.sourceId() == null))) {
            throw VolunteerException.invalid("Choose a valid activity source.");
        }
    }
    private UUID createLocked(UUID actor, Draft d) {
        if (d == null) {
            throw VolunteerException.invalid("A shift draft is required.");
        }
        ServingRules.id(d.requestId());
        ServingRules.id(d.teamId());
        ServingRules.id(d.roleId());
        Team team = teamRepository.lock(d.teamId())
            .filter(Team::active)
            .orElseThrow(() -> VolunteerException.missing("Active team"));
        String hash = Fingerprint.of(d.teamId(), d.roleId(), d.sourceKind(), d.sourceId(),
            ServingRules.instant(d.startsAt()), ServingRules.instant(d.endsAt()), d.positions());
        Optional<Shift> previous = roster.shiftRequest(d.requestId());
        if (previous.isPresent()) {
            Shift old = previous.orElseThrow();
            if (!old.requestHash().equals(hash)) {
                throw VolunteerException.conflict("This request identifier was already used for another shift.");
            }
            return old.id();
        }
        teams.role(d.roleId())
            .filter(r -> r.teamId().equals(d.teamId()) && r.active())
            .orElseThrow(() -> VolunteerException.missing("Active team role"));
        Shift shift = new Shift(UUID.randomUUID(), d.requestId(), hash, d.teamId(), d.roleId(), d.sourceKind(),
            d.sourceId(), null, d.startsAt(), d.endsAt(), d.positions(), "OPEN", 0, 0);
        shift = shift.sourceRevision(activities.revision(shift));
        activities.require(shift);
        repository.insertShift(shift);
        teamRepository.save(team.touch());
        audit.record(actor, "shift.created", shift.id(), clock.instant());
        return shift.id();
    }
    public List<UUID> bulk(UUID actor, List<Draft> drafts) {
        if (drafts == null || drafts.isEmpty() || drafts.size() > 25 || drafts.stream().anyMatch(Objects::isNull)) {
            throw VolunteerException.invalid("Create between 1 and 25 shifts together.");
        }
        return tx.execute(() -> {
            drafts.forEach(RosterPlanning::validateSource);
            profiles.lockAll(List.of(),
                drafts.stream()
                    .filter(d -> d.sourceId() != null)
                    .map(d -> new ServingActivityLocks.Activity(d.sourceKind(), d.sourceId()))
                    .toList());
            drafts.stream()
                .map(Draft::teamId)
                .distinct()
                .sorted(Comparator.comparing(UUID::toString))
                .forEach(id -> teamRepository.lock(id).orElseThrow(() -> VolunteerException.missing("Team")));
            return drafts.stream().map(d -> createLocked(actor, d)).toList();
        });
    }
    public void update(UUID actor, UUID id, long expected, Instant from, Instant until, int positions, String status) {
        tx.execute(() -> {
            Shift before = roster.shift(id).orElseThrow(() -> VolunteerException.missing("Shift"));
            profiles.lockAll(roster.assignments(id)
                .stream()
                .filter(a -> !a.status().equals("CANCELLED"))
                .map(Assignment::volunteerId)
                .distinct()
                .toList(), activity(before));
            Shift shift = repository.lockShift(id).orElseThrow(() -> VolunteerException.missing("Shift"));
            ServingRules.version(shift.version(), expected);
            List<Assignment> assignments =
                roster.assignments(id).stream().filter(a -> !a.status().equals("CANCELLED")).toList();
            if (positions < assignments.size() && !status.equals("CANCELLED")) {
                throw VolunteerException.conflict("Positions cannot be reduced below reserved assignments.");
            }
            Shift changed = shift.change(from, until, positions, status);
            Shift revised = changed
                .sourceRevision(status.equals("CANCELLED") ? shift.sourceRevision() : activities.revision(changed));
            if (!status.equals("CANCELLED")) {
                activities.require(revised);
                for (Assignment a : assignments) {
                    if (roster.volunteerAssignments(a.volunteerId(), revised.startsAt(), revised.endsAt())
                        .stream()
                        .anyMatch(other -> !other.id().equals(a.id()))) {
                        throw VolunteerException.conflict("The revised shift overlaps a volunteer's other assignment.");
                    }
                }
            }
            repository.saveShift(revised);
            for (Assignment a : assignments) {
                withdraw(a.id());
                if (status.equals("CANCELLED")) {
                    repository.saveAssignment(a.change("CANCELLED"));
                } else if (a.status().equals("CONFIRMED")
                    && (!shift.startsAt().equals(revised.startsAt()) || !shift.endsAt().equals(revised.endsAt())
                        || !Objects.equals(shift.sourceRevision(), revised.sourceRevision()))) {
                    repository.saveAssignment(a.change("PROPOSED"));
                }
                notifyVolunteer(a.volunteerId(), MemberNotifications.Kind.DUTY_CHANGED,
                    "shift:" + id + ":" + revised.version());
                repository.event(a.id(), actor, "SHIFT_CHANGED", "Review the revised shift details.", clock.instant());
            }
            audit.record(actor, "shift.changed", id, clock.instant());
            return null;
        });
    }
    public UUID assign(UUID actor, UUID request, UUID shiftId, long expected, UUID volunteer, String status) {
        if (status == null || !List.of("PROPOSED", "CONFIRMED").contains(status)) {
            throw VolunteerException.invalid("Choose a proposed or confirmed commitment.");
        }
        return tx.execute(() -> {
            Shift before = roster.shift(shiftId).orElseThrow(() -> VolunteerException.missing("Shift"));
            Volunteer v = profiles.lockAll(List.of(volunteer), activity(before)).getFirst();
            if (!v.status().equals("ACTIVE")) {
                throw VolunteerException.conflict("Only active volunteers can be scheduled.");
            }
            Shift shift = repository.lockShift(shiftId).orElseThrow(() -> VolunteerException.missing("Shift"));
            String hash = Fingerprint.of(shiftId, volunteer, status);
            Optional<Assignment> previous = roster.assignmentRequest(request);
            if (previous.isPresent()) {
                Assignment old = previous.orElseThrow();
                if (!old.requestHash().equals(hash)) {
                    throw VolunteerException.conflict("This request identifier was already used.");
                }
                return old.id();
            }
            ServingRules.version(shift.version(), expected);
            if (!shift.status().equals("OPEN") || !shift.endsAt().isAfter(clock.instant())) {
                throw VolunteerException.conflict("Choose an upcoming open shift.");
            }
            activities.require(shift);
            teams.team(shift.teamId())
                .filter(Team::active)
                .orElseThrow(() -> VolunteerException.conflict("The team is inactive."));
            teams.role(shift.roleId())
                .filter(ServingRole::active)
                .orElseThrow(() -> VolunteerException.conflict("The role is inactive."));
            if (roster.assignments(shiftId).stream().filter(a -> !a.status().equals("CANCELLED")).count() >= shift
                .positions()) {
                throw VolunteerException.conflict("All positions are reserved.");
            }
            if (!roster.volunteerAssignments(volunteer, shift.startsAt(), shift.endsAt()).isEmpty()) {
                throw VolunteerException.conflict("The volunteer has an overlapping assignment.");
            }
            Assignment assignment =
                new Assignment(UUID.randomUUID(), request, hash, shiftId, volunteer, status, 0, actor, clock.instant());
            repository.insertAssignment(assignment);
            repository.saveShift(shift.touch());
            repository.event(assignment.id(), actor, "ASSIGNED", status, clock.instant());
            notifyVolunteer(volunteer, MemberNotifications.Kind.DUTY_ASSIGNED, assignment.id().toString());
            audit.record(actor, "assignment.created", assignment.id(), clock.instant());
            return assignment.id();
        });
    }
    private List<ServingActivityLocks.Activity> activity(Shift shift) {
        return shift.sourceId() == null ? List.of()
            : List.of(new ServingActivityLocks.Activity(shift.sourceKind(), shift.sourceId()));
    }
    private record Locked(Assignment assignment, Shift shift) {}
    private Locked lock(UUID id) {
        Assignment before = roster.assignment(id).orElseThrow(() -> VolunteerException.missing("Assignment"));
        profiles.lock(before.volunteerId());
        Shift shift = repository.lockShift(before.shiftId()).orElseThrow();
        return new Locked(repository.lockAssignment(id).orElseThrow(), shift);
    }
    public void change(UUID actor, UUID id, long expected, String status, String reason) {
        String note = ServingRules.text(reason, 500);
        tx.execute(() -> {
            Locked locked = lock(id);
            Assignment a = locked.assignment();
            ServingRules.version(a.version(), expected);
            if (!status.equals("CANCELLED")) {
                if (!locked.shift().status().equals("OPEN")) {
                    throw VolunteerException.conflict("The shift is cancelled.");
                }
                activities.require(locked.shift());
            }
            withdraw(id);
            repository.saveAssignment(a.change(status));
            repository.saveShift(locked.shift().touch());
            repository.event(id, actor, "STATUS_CHANGED", note, clock.instant());
            notifyVolunteer(a.volunteerId(), MemberNotifications.Kind.DUTY_CHANGED,
                "assignment:" + id + ":" + (a.version() + 1));
            audit.record(actor, "assignment.changed", id, clock.instant());
            return null;
        });
    }
    private void withdraw(UUID assignment) {
        for (Invitation invitation : roster.invitations(assignment)) {
            if (invitation.status().equals("PENDING")) {
                Invitation locked = repository.lockInvitation(invitation.id()).orElseThrow();
                if (locked.status().equals("PENDING")) {
                    repository.saveInvitation(locked.respond("WITHDRAWN", clock.instant()));
                }
            }
        }
    }
    public UUID invite(UUID actor, UUID id, long expected, Instant expires) {
        return tx.execute(() -> {
            Locked locked = lock(id);
            Assignment a = locked.assignment();
            Shift shift = locked.shift();
            ServingRules.version(a.version(), expected);
            Instant at = ServingRules.instant(expires);
            if (a.status().equals("CANCELLED") || !shift.status().equals("OPEN") || !at.isAfter(clock.instant())
                || at.isAfter(shift.startsAt())) {
                throw VolunteerException
                    .invalid("Invitation expiry must be in the future and no later than shift start.");
            }
            activities.require(shift);
            withdraw(id);
            UUID invitation = UUID.randomUUID();
            repository.insertInvitation(
                new Invitation(invitation, id, a.version(), shift.scheduleRevision(), at, "PENDING", 0, null));
            repository.event(id, actor, "INVITED", "Volunteer response requested.", clock.instant());
            notifyVolunteer(a.volunteerId(), MemberNotifications.Kind.VOLUNTEER_INVITATION, invitation.toString());
            audit.record(actor, "invitation.created", invitation, clock.instant());
            return invitation;
        });
    }
    public void respond(UUID actor, UUID account, UUID id, long expected, String decision) {
        tx.execute(() -> {
            Invitation before = roster.invitation(id).orElseThrow(() -> VolunteerException.missing("Invitation"));
            Locked locked = lock(before.assignmentId());
            Assignment a = locked.assignment();
            Volunteer volunteer = volunteers.volunteer(a.volunteerId()).orElseThrow();
            if (!account.equals(volunteer.accountId()) || !volunteer.status().equals("ACTIVE")) {
                throw VolunteerException.forbidden();
            }
            Invitation invitation = repository.lockInvitation(id).orElseThrow();
            ServingRules.version(invitation.version(), expected);
            if (!invitation.status().equals("PENDING") || !invitation.expiresAt().isAfter(clock.instant())
                || invitation.assignmentVersion() != a.version()
                || invitation.shiftVersion() != locked.shift().scheduleRevision() || a.status().equals("CANCELLED")
                || !locked.shift().status().equals("OPEN")) {
                throw VolunteerException
                    .conflict("This invitation expired or the shift changed. Request a new invitation.");
            }
            activities.require(locked.shift());
            if (decision == null || !List.of("ACCEPTED", "DECLINED").contains(decision)) {
                throw VolunteerException.invalid("Choose accepted or declined.");
            }
            repository.saveShift(locked.shift().touch());
            repository.saveInvitation(invitation.respond(decision, clock.instant()));
            repository.saveAssignment(a.change(decision.equals("ACCEPTED") ? "CONFIRMED" : "CANCELLED"));
            repository.event(a.id(), actor, decision, "Volunteer response.", clock.instant());
            audit.record(actor, "invitation.responded", id, clock.instant());
            return null;
        });
    }
    public int expire(UUID actor, int limit) {
        if (limit < 1 || limit > 100) {
            throw VolunteerException.invalid("Process between 1 and 100 invitations.");
        }
        int count = 0;
        for (Invitation candidate : roster.expirable(clock.instant(), limit)) {
            boolean changed = tx.execute(() -> {
                Locked locked = lock(candidate.assignmentId());
                Invitation invitation = repository.lockInvitation(candidate.id()).orElseThrow();
                if (!invitation.status().equals("PENDING") || invitation.expiresAt().isAfter(clock.instant())) {
                    return false;
                }
                repository.saveInvitation(invitation.respond("EXPIRED", clock.instant()));
                if (locked.assignment().version() == invitation.assignmentVersion()
                    && locked.shift().scheduleRevision() == invitation.shiftVersion()
                    && locked.assignment().status().equals("PROPOSED")) {
                    repository.saveAssignment(locked.assignment().change("CANCELLED"));
                    repository.saveShift(locked.shift().touch());
                }
                repository.event(invitation.assignmentId(), actor, "INVITATION_EXPIRED", "Invitation hold released.",
                    clock.instant());
                audit.record(actor, "invitation.expired", invitation.id(), clock.instant());
                return true;
            });
            if (changed) {
                count++;
            }
        }
        return count;
    }
}
