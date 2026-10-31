package org.onechurch.volunteering.infrastructure;

import org.onechurch.communications.application.contract.DeliveryEligibility;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.identityaccess.application.contract.ResponsibleAccountDirectory;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.onechurch.volunteering.application.ActivityRegistry;
import org.onechurch.volunteering.application.ReminderReview;
import org.onechurch.volunteering.application.contract.ReminderReader;
import org.onechurch.volunteering.application.contract.RosterReader;
import org.onechurch.volunteering.application.contract.VolunteerReader;
import org.onechurch.volunteering.domain.Assignment;
import org.onechurch.volunteering.domain.Shift;
import org.onechurch.volunteering.domain.Volunteer;
import org.onechurch.volunteering.domain.VolunteerException;
import org.springframework.stereotype.Component;

import java.time.Instant;
import java.util.UUID;

@Component
public class VolunteerReminderEligibility implements DeliveryEligibility {
    private final PermissionChecker auth;
    private final ResponsibleAccountDirectory accounts;
    private final ReminderReader reminders;
    private final RosterReader roster;
    private final VolunteerReader volunteers;
    private final PersonEmailDirectory emails;
    private final PersonDirectory people;
    private final ActivityRegistry activities;
    public VolunteerReminderEligibility(PermissionChecker auth, ResponsibleAccountDirectory accounts,
        ReminderReader reminders, RosterReader roster, VolunteerReader volunteers, PersonEmailDirectory emails,
        PersonDirectory people, ActivityRegistry activities) {
        this.auth = auth;
        this.accounts = accounts;
        this.reminders = reminders;
        this.roster = roster;
        this.volunteers = volunteers;
        this.emails = emails;
        this.people = people;
        this.activities = activities;
    }
    public String sourceContext() {
        return "volunteering";
    }
    public void authorize(DeliverySnapshot delivery, UUID actor, String operation) {
        auth.requirePermission(actor, "volunteering.communications.send");
    }
    public boolean eligible(DeliverySnapshot d, Instant at) {
        if (!accounts.eligible(d.approvedBy(), "volunteering.communications.send")
            || !ReminderReview.TEMPLATE.equals(d.templateKey()) || d.templateVersion() == null) {
            return false;
        }
        return reminders.reminder(d.communicationId())
            .filter(r -> r.deliveryId().equals(d.id()) && r.createdBy().equals(d.approvedBy())
                && r.contactId().equals(d.contactId()) && r.reviewHash().equals(d.reviewHash()))
            .map(r -> {
                Assignment a = roster.assignment(r.assignmentId()).orElse(null);
                if (a == null) {
                    return false;
                }
                Shift s = roster.shift(a.shiftId()).orElseThrow();
                Volunteer v = volunteers.volunteer(a.volunteerId()).orElseThrow();
                try {
                    activities.require(s);
                } catch (VolunteerException unavailable) {
                    return false;
                }
                return a.status().equals("CONFIRMED") && s.status().equals("OPEN") && s.startsAt().isAfter(at)
                    && v.status().equals("ACTIVE")
                    && people.get(v.personId()).map(PersonDirectory.Entry::available).orElse(false)
                    && r.assignmentVersion() == a.version() && r.shiftVersion() == s.scheduleRevision()
                    && emails.active(v.personId())
                        .stream()
                        .anyMatch(e -> e.id().equals(r.contactId()) && e.value().equals(d.recipient()))
                    && ReminderReview
                        .hash(a, s, r.contactId(), d.recipient(), d.subject(), d.body(), d.templateVersion())
                        .equals(d.reviewHash());
            })
            .orElse(false);
    }
}
