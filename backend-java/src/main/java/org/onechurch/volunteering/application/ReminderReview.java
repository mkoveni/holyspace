package org.onechurch.volunteering.application;

import org.onechurch.communications.application.contract.EmailTemplates;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.onechurch.volunteering.application.contract.RosterReader;
import org.onechurch.volunteering.application.contract.TeamReader;
import org.onechurch.volunteering.application.contract.VolunteerReader;
import org.onechurch.volunteering.domain.Assignment;
import org.onechurch.volunteering.domain.ServingRules;
import org.onechurch.volunteering.domain.Shift;
import org.onechurch.volunteering.domain.Volunteer;
import org.onechurch.volunteering.domain.VolunteerException;

import java.time.Instant;
import java.util.List;
import java.util.Map;
import java.util.UUID;

public final class ReminderReview {
    public static final String TEMPLATE = "volunteering.shift-reminder";
    public record Review(UUID assignmentId, long assignmentVersion, long shiftRevision, UUID contactId,
        String recipient, String subject, String html, String hash, long templateVersion) {
        @Override
        public String toString() {
            return "Review[assignmentId=" + assignmentId + "]";
        }
    }
    private final RosterReader roster;
    private final VolunteerReader volunteers;
    private final TeamReader teams;
    private final PersonDirectory people;
    private final PersonEmailDirectory emails;
    private final EmailTemplates templates;
    public ReminderReview(RosterReader roster, VolunteerReader volunteers, TeamReader teams, PersonDirectory people,
        PersonEmailDirectory emails, EmailTemplates templates) {
        this.roster = roster;
        this.volunteers = volunteers;
        this.teams = teams;
        this.people = people;
        this.emails = emails;
        this.templates = templates;
    }
    public List<PersonEmailDirectory.Email> contacts(UUID assignmentId) {
        Assignment a = roster.assignment(assignmentId).orElseThrow(() -> VolunteerException.missing("Assignment"));
        Volunteer v = volunteers.volunteer(a.volunteerId()).orElseThrow();
        return emails.active(v.personId());
    }
    public Review review(UUID assignmentId, UUID contactId, String message, Instant now) {
        String note = ServingRules.text(message, 2000);
        Assignment a = roster.assignment(assignmentId).orElseThrow(() -> VolunteerException.missing("Assignment"));
        Shift s = roster.shift(a.shiftId()).orElseThrow();
        Volunteer v = volunteers.volunteer(a.volunteerId()).orElseThrow();
        PersonDirectory.Entry person = people.get(v.personId())
            .filter(PersonDirectory.Entry::available)
            .orElseThrow(() -> VolunteerException.conflict("This person is unavailable."));
        if (!v.status().equals("ACTIVE") || !a.status().equals("CONFIRMED") || !s.status().equals("OPEN")
            || !s.startsAt().isAfter(now)) {
            throw VolunteerException.conflict("Reminders require an active volunteer with a confirmed upcoming shift.");
        }
        String recipient = emails.active(v.personId())
            .stream()
            .filter(e -> e.id().equals(contactId))
            .map(PersonEmailDirectory.Email::value)
            .findFirst()
            .orElseThrow(() -> VolunteerException.conflict("Choose a current email address."));
        EmailTemplates.Rendered content = templates.render(TEMPLATE,
            Map.of("name", person.name(), "team", teams.team(s.teamId()).orElseThrow().name(), "role",
                teams.role(s.roleId()).orElseThrow().name(), "startsAt", s.startsAt().toString(), "endsAt",
                s.endsAt().toString(), "message", note));
        return new Review(a.id(), a.version(), s.scheduleRevision(), contactId, recipient, content.subject(),
            content.html(), hash(a, s, contactId, recipient, content.subject(), content.html(), content.version()),
            content.version());
    }
    public static String hash(Assignment a, Shift s, UUID contact, String recipient, String subject, String html,
        long templateVersion) {
        return Fingerprint.of(a.id(), a.version(), s.scheduleRevision(), contact, recipient, subject, html,
            templateVersion);
    }
}
