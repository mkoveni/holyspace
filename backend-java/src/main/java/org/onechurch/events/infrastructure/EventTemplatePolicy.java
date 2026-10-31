package org.onechurch.events.infrastructure;
import org.onechurch.communications.application.contract.TemplatePublicationPolicy;
import org.springframework.stereotype.Component;

import java.util.List;
import java.util.Optional;
import java.util.Set;
@Component
public class EventTemplatePolicy implements TemplatePublicationPolicy {
    public Optional<String> violation(String key, String subject, String html, List<String> variables) {
        if (!Set.of("events.confirmation", "events.reminder", "events.cancellation", "events.rescheduled")
            .contains(key)) {
            return Optional.empty();
        }
        List<String> required = List.of("name", "event", "startsAt", "endsAt", "timeZone", "venue", "message");
        return variables.containsAll(required) && required.stream()
            .allMatch(v -> (subject + html).contains("{{" + v + "}}")) ? Optional.empty() : Optional.of(
                "Event templates must retain name, event, startsAt, endsAt, timeZone, venue and message variables.");
    }
}
