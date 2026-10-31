package org.onechurch.volunteering.application;

import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.volunteering.application.contract.RosterReader;
import org.onechurch.volunteering.application.contract.TeamReader;
import org.onechurch.volunteering.application.contract.VolunteerReader;
import org.onechurch.volunteering.domain.Assignment;
import org.onechurch.volunteering.domain.AvailabilityException;
import org.onechurch.volunteering.domain.AvailabilityRule;
import org.onechurch.volunteering.domain.CredentialType;
import org.onechurch.volunteering.domain.Invitation;
import org.onechurch.volunteering.domain.RoleApproval;
import org.onechurch.volunteering.domain.ServingRole;
import org.onechurch.volunteering.domain.ServingRules;
import org.onechurch.volunteering.domain.Shift;
import org.onechurch.volunteering.domain.Team;
import org.onechurch.volunteering.domain.TeamMembership;
import org.onechurch.volunteering.domain.Volunteer;
import org.onechurch.volunteering.domain.VolunteerException;

import java.time.Clock;
import java.time.Duration;
import java.time.Instant;
import java.util.List;
import java.util.UUID;

public final class ServingViews {
    public record Person(Volunteer volunteer, String name) {}
    public record Credential(UUID id, UUID typeId, String type, Instant validFrom, Instant validUntil,
        Instant revokedAt) {}
    public record Profile(Person person, List<Credential> credentials, List<AvailabilityRule> weekly,
        List<AvailabilityException> exceptions) {}
    public record Volunteers(List<Person> volunteers, long total) {}
    public record Teams(List<Team> teams, long total) {}
    public record TeamDetails(Team team, List<ServingRole> roles, List<TeamMembership> memberships,
        List<RoleApproval> approvals) {}
    public record Booking(Assignment assignment, String name, VolunteerReadiness.Assessment readiness,
        List<InvitationView> invitations) {}
    public record ShiftView(Shift shift, String team, String role, List<Booking> assignments, int reserved,
        int confirmed, int ready, int vacancies) {}
    public record Shifts(List<ShiftView> shifts, long total) {}
    public record InvitationView(Invitation invitation, String effectiveStatus, String team, String role,
        Instant startsAt, Instant endsAt, VolunteerReadiness.Assessment readiness) {}
    private final VolunteerReader volunteers;
    private final TeamReader teams;
    private final RosterReader roster;
    private final PersonDirectory people;
    private final VolunteerReadiness readiness;
    private final Clock clock;
    public ServingViews(VolunteerReader volunteers, TeamReader teams, RosterReader roster, PersonDirectory people,
        VolunteerReadiness readiness, Clock clock) {
        this.volunteers = volunteers;
        this.teams = teams;
        this.roster = roster;
        this.people = people;
        this.readiness = readiness;
        this.clock = clock;
    }
    public static void page(int limit, int offset) {
        if (limit < 1 || limit > 100 || offset < 0 || offset > 100000) {
            throw VolunteerException.invalid("Choose a bounded page of 1–100 records.");
        }
    }
    public static void range(Instant from, Instant until) {
        ServingRules.period(from, until);
        if (Duration.between(from, until).compareTo(Duration.ofDays(90)) > 0) {
            throw VolunteerException.invalid("Choose at most 90 days.");
        }
    }
    public Person person(Volunteer v) {
        return new Person(v, people.get(v.personId()).map(PersonDirectory.Entry::name).orElse("Unavailable person"));
    }
    public Volunteers volunteers(String search, int limit, int offset) {
        page(limit, offset);
        if (search == null || search.length() > 150) {
            throw VolunteerException.invalid("Use at most 150 search characters.");
        }
        return new Volunteers(volunteers.volunteers(search, limit, offset).stream().map(this::person).toList(),
            volunteers.volunteerCount(search));
    }
    public Profile profile(UUID id) {
        Volunteer v = volunteers.volunteer(id).orElseThrow(() -> VolunteerException.missing("Volunteer"));
        List<CredentialType> types = volunteers.credentialTypes();
        return new Profile(person(v),
            volunteers.credentials(id)
                .stream()
                .map(c -> new Credential(c.id(), c.typeId(),
                    types.stream()
                        .filter(t -> t.id().equals(c.typeId()))
                        .map(CredentialType::name)
                        .findFirst()
                        .orElse("Credential"),
                    c.validFrom(), c.validUntil(), c.revokedAt()))
                .toList(),
            volunteers.availability(id, v.availabilityRevision()), volunteers.exceptions(id, v.availabilityRevision()));
    }
    public Teams teams(int limit, int offset) {
        page(limit, offset);
        return new Teams(teams.teams(limit, offset), teams.teamCount());
    }
    public TeamDetails team(UUID id) {
        Team t = teams.team(id).orElseThrow(() -> VolunteerException.missing("Team"));
        List<ServingRole> roles = teams.roles(id);
        return new TeamDetails(t, roles, teams.memberships(id, null),
            roles.stream().flatMap(role -> teams.approvals(role.id(), null).stream()).toList());
    }
    public ShiftView shift(UUID id) {
        Shift shift = roster.shift(id).orElseThrow(() -> VolunteerException.missing("Shift"));
        List<Booking> bookings = roster.assignments(id)
            .stream()
            .map(a -> new Booking(a, person(volunteers.volunteer(a.volunteerId()).orElseThrow()).name(),
                readiness.assess(a.volunteerId(), shift, a.id()),
                roster.invitations(a.id()).stream().map(this::invitation).toList()))
            .toList();
        int reserved = (int) bookings.stream().filter(b -> !b.assignment().status().equals("CANCELLED")).count();
        int confirmed = (int) bookings.stream().filter(b -> b.assignment().status().equals("CONFIRMED")).count();
        int ready = (int) bookings.stream()
            .filter(b -> b.assignment().status().equals("CONFIRMED") && b.readiness().ready())
            .count();
        return new ShiftView(shift, teams.team(shift.teamId()).orElseThrow().name(),
            teams.role(shift.roleId()).orElseThrow().name(), bookings, reserved, confirmed, ready,
            Math.max(0, shift.positions() - reserved));
    }
    public Shifts shifts(Instant from, Instant until, UUID team, int limit, int offset) {
        page(limit, offset);
        range(from, until);
        return new Shifts(roster.shifts(from, until, team, limit, offset).stream().map(s -> shift(s.id())).toList(),
            roster.shiftCount(from, until, team));
    }
    public InvitationView invitation(Invitation i) {
        Assignment a = roster.assignment(i.assignmentId()).orElseThrow();
        Shift s = roster.shift(a.shiftId()).orElseThrow();
        String status = i.status();
        if (status.equals("PENDING")) {
            if (a.status().equals("CANCELLED") || s.status().equals("CANCELLED") || a.version() != i.assignmentVersion()
                || s.scheduleRevision() != i.shiftVersion()) {
                status = "WITHDRAWN";
            } else if (!i.expiresAt().isAfter(clock.instant())) {
                status = "EXPIRED";
            }
        }
        return new InvitationView(i, status, teams.team(s.teamId()).orElseThrow().name(),
            teams.role(s.roleId()).orElseThrow().name(), s.startsAt(), s.endsAt(),
            readiness.assess(a.volunteerId(), s, a.id()));
    }
    public List<InvitationView> own(UUID account, int limit, int offset) {
        page(limit, offset);
        return volunteers.byAccount(account)
            .map(v -> roster.myInvitations(v.id(), limit, offset).stream().map(this::invitation).toList())
            .orElse(List.of());
    }
}
