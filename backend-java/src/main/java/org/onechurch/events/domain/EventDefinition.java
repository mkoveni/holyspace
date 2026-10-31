package org.onechurch.events.domain;
import java.util.Set;
import java.util.UUID;
public record EventDefinition(UUID id, String title, String type, String description, boolean active, long version) {
    public EventDefinition {
        EventRules.id(id);
        title = EventRules.text(title, 200);
        description = EventRules.optional(description, 5000);
        if (type == null || !Set.of("SERVICE", "MEETING", "CONFERENCE", "OUTREACH", "CLASS", "OTHER").contains(type)) {
            throw EventException.invalid("Choose an event type.");
        }
    }
    public EventDefinition edit(String title, String type, String description, boolean active) {
        return new EventDefinition(id, title, type, description, active, version + 1);
    }
    public EventDefinition touch() {
        return edit(title, type, description, active);
    }
}
