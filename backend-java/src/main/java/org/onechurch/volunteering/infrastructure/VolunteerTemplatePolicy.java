package org.onechurch.volunteering.infrastructure;

import org.onechurch.communications.application.contract.TemplatePublicationPolicy;
import org.onechurch.volunteering.application.ReminderReview;
import org.springframework.stereotype.Component;

import java.util.List;
import java.util.Optional;

@Component
public class VolunteerTemplatePolicy implements TemplatePublicationPolicy {
    public Optional<String> violation(String key, String subject, String html, List<String> variables) {
        if (!ReminderReview.TEMPLATE.equals(key)) {
            return Optional.empty();
        }
        return List.of("name", "team", "role", "startsAt", "endsAt", "message")
            .stream()
            .allMatch(v -> variables.contains(v) && (subject + html).contains("{{" + v + "}}")) ? Optional.empty()
                : Optional
                    .of("Volunteer reminders must retain name, team, role, startsAt, endsAt and message variables.");
    }
}
