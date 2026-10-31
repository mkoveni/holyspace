package org.onechurch.volunteering.application;

import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.volunteering.application.contract.RosterReader;
import org.onechurch.volunteering.application.contract.StaffingDirectory;
import org.onechurch.volunteering.application.contract.StaffingUnavailable;
import org.onechurch.volunteering.application.contract.TeamReader;
import org.onechurch.volunteering.application.contract.VolunteerReader;
import org.onechurch.volunteering.domain.Assignment;
import org.onechurch.volunteering.domain.ServingRole;
import org.onechurch.volunteering.domain.ServingRules;
import org.onechurch.volunteering.domain.Shift;
import org.onechurch.volunteering.domain.Volunteer;
import org.onechurch.volunteering.domain.VolunteerException;

import java.time.Instant;
import java.util.ArrayList;
import java.util.List;
import java.util.UUID;

public final class PublishedStaffingDirectory implements StaffingDirectory {
    private final RosterReader roster;
    private final VolunteerReader volunteers;
    private final TeamReader teams;
    private final VolunteerReadiness readiness;
    private final PersonDirectory people;
    public PublishedStaffingDirectory(RosterReader roster, VolunteerReader volunteers, TeamReader teams,
        VolunteerReadiness readiness, PersonDirectory people) {
        this.roster = roster;
        this.volunteers = volunteers;
        this.teams = teams;
        this.readiness = readiness;
        this.people = people;
    }
    public Assessment assess(UUID id, UUID session, String responsibility, Instant from, Instant until) {
        try {
            return assessment(id, session, responsibility, from, until);
        } catch (VolunteerException unavailable) {
            throw new StaffingUnavailable();
        }
    }
    private Assessment assessment(UUID id, UUID session, String responsibility, Instant from, Instant until) {
        Assignment assignment =
            roster.assignment(id).orElseThrow(() -> VolunteerException.missing("Volunteer assignment"));
        Shift shift = roster.shift(assignment.shiftId()).orElseThrow();
        Volunteer volunteer = volunteers.volunteer(assignment.volunteerId()).orElseThrow();
        ServingRole role = teams.role(shift.roleId()).orElseThrow();
        List<String> issues = new ArrayList<>(readiness.assess(volunteer.id(), shift, id).issues());
        if (!assignment.status().equals("CONFIRMED")) {
            issues.add("The volunteer assignment is not confirmed.");
        }
        if (!shift.sourceKind().equals("CHILDREN_SESSION") || !session.equals(shift.sourceId())) {
            issues.add("Choose an assignment linked to this exact children session.");
        }
        if (!role.childrenRole().equals(responsibility)) {
            issues.add("The role does not approve this children responsibility.");
        }
        if (!ServingRules.covers(shift.startsAt(), shift.endsAt(), from, until)) {
            issues.add("The assignment must cover the entire session duty interval.");
        }
        String name = people.get(volunteer.personId()).map(PersonDirectory.Entry::name).orElse("Unavailable person");
        return new Assessment(id, volunteer.personId(), name, role.childrenRole(), issues.isEmpty(),
            List.copyOf(issues));
    }
    public List<Assessment> candidates(UUID session, Instant from, Instant until) {
        List<Shift> shifts = roster.sourceShifts("CHILDREN_SESSION", session, 101);
        if (shifts.size() > 100) {
            throw new StaffingUnavailable();
        }
        return shifts.stream()
            .filter(s -> s.sourceKind().equals("CHILDREN_SESSION") && session.equals(s.sourceId()))
            .flatMap(s -> roster.assignments(s.id())
                .stream()
                .filter(a -> !a.status().equals("CANCELLED"))
                .map(a -> assess(a.id(), session, teams.role(s.roleId()).orElseThrow().childrenRole(), from, until)))
            .toList();
    }
}
