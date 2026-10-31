package org.onechurch.childrenministry.incidents.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.util.UUID;
public record IncidentEvent(UUID id, UUID incidentId, long revision, String type, UUID actorId, Instant occurredAt,
    String note, UUID guardianId) {
    public IncidentEvent {
        note = ChildrenRules.text(note, 3000);
        occurredAt = ChildrenRules.instant(occurredAt);
    }
    @Override
    public String toString() {
        return "IncidentEvent[id=" + id + ", type=" + type + "]";
    }
}
