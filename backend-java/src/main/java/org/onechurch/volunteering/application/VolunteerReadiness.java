package org.onechurch.volunteering.application;

import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.volunteering.application.contract.RosterReader;
import org.onechurch.volunteering.application.contract.TeamReader;
import org.onechurch.volunteering.application.contract.VolunteerReader;
import org.onechurch.volunteering.domain.Assignment;
import org.onechurch.volunteering.domain.Availability;
import org.onechurch.volunteering.domain.Credential;
import org.onechurch.volunteering.domain.CredentialType;
import org.onechurch.volunteering.domain.ServingRole;
import org.onechurch.volunteering.domain.Shift;
import org.onechurch.volunteering.domain.Team;
import org.onechurch.volunteering.domain.Volunteer;
import org.onechurch.volunteering.domain.VolunteerException;

import java.util.ArrayList;
import java.util.List;
import java.util.UUID;

public final class VolunteerReadiness {
    public record Assessment(boolean ready, List<String> issues) {}
    private final VolunteerReader volunteers;
    private final TeamReader teams;
    private final RosterReader roster;
    private final PersonDirectory people;
    private final ActivityRegistry activities;
    public VolunteerReadiness(VolunteerReader volunteers, TeamReader teams, RosterReader roster, PersonDirectory people,
        ActivityRegistry activities) {
        this.volunteers = volunteers;
        this.teams = teams;
        this.roster = roster;
        this.people = people;
        this.activities = activities;
    }
    public Assessment assess(UUID volunteerId, Shift shift, UUID ignoreAssignment) {
        Volunteer volunteer =
            volunteers.volunteer(volunteerId).orElseThrow(() -> VolunteerException.missing("Volunteer"));
        ServingRole role = teams.role(shift.roleId()).orElseThrow(() -> VolunteerException.missing("Role"));
        List<String> issues = new ArrayList<>();
        if (!volunteer.status().equals("ACTIVE")) {
            issues.add("The volunteer is paused or archived.");
        }
        if (people.get(volunteer.personId()).filter(PersonDirectory.Entry::available).isEmpty()) {
            issues.add("The People record is unavailable.");
        }
        if (!teams.team(shift.teamId()).filter(Team::active).isPresent() || !role.active()) {
            issues.add("The team or role is inactive.");
        }
        if (!shift.status().equals("OPEN")) {
            issues.add("The shift is cancelled.");
        }
        try {
            activities.require(shift);
        } catch (VolunteerException invalid) {
            issues.add(invalid.getMessage());
        }
        if (teams.memberships(shift.teamId(), volunteerId)
            .stream()
            .noneMatch(m -> m.covers(shift.startsAt(), shift.endsAt()))) {
            issues.add("Team membership must cover the entire shift.");
        }
        if (teams.approvals(shift.roleId(), volunteerId)
            .stream()
            .noneMatch(a -> a.covers(shift.startsAt(), shift.endsAt()))) {
            issues.add("Role approval must cover the entire shift.");
        }
        List<Credential> credentials = volunteers.credentials(volunteerId);
        for (UUID required : role.requiredCredentials()) {
            if (credentials.stream()
                .noneMatch(c -> c.typeId().equals(required) && c.covers(shift.startsAt(), shift.endsAt()))) {
                String name = volunteers.credentialTypes()
                    .stream()
                    .filter(t -> t.id().equals(required))
                    .map(CredentialType::name)
                    .findFirst()
                    .orElse("Required credential");
                issues.add(name + " must be verified and valid through the end of this shift.");
            }
        }
        if (!Availability.covers(shift.startsAt(), shift.endsAt(),
            volunteers.availability(volunteerId, volunteer.availabilityRevision()),
            volunteers.exceptions(volunteerId, volunteer.availabilityRevision()))) {
            issues.add("Availability is unknown or does not cover the entire shift.");
        }
        List<Assignment> bookings = roster.volunteerAssignments(volunteerId, shift.startsAt(), shift.endsAt());
        if (bookings.size() > 1000) {
            throw VolunteerException.conflict("Too many assignments in this interval. Narrow the schedule.");
        }
        if (bookings.stream().anyMatch(a -> !a.id().equals(ignoreAssignment))) {
            issues.add("There is an overlapping assignment.");
        }
        return new Assessment(issues.isEmpty(), List.copyOf(issues));
    }
}
