package org.onechurch.childrenministry.messaging.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
public record GuardianCommunication(UUID id, UUID childId, UUID authorityId, UUID guardianId, UUID programmeId,
    UUID policyId, UUID sessionId, String type, String message, LocalDate dueOn, String status, UUID createdBy,
    Instant createdAt, UUID updatedBy, Instant updatedAt, String contactReference, long version) {
    public GuardianCommunication {
        message = ChildrenRules.text(message, 3000);
        contactReference = ChildrenRules.optional(contactReference, 500);
        createdAt = ChildrenRules.instant(createdAt);
        updatedAt = ChildrenRules.instant(updatedAt);
        if (type == null || !List.of("CONSENT_RENEWAL", "SESSION_REMINDER").contains(type) || status == null
            || !List.of("DRAFT", "CONTACTED", "CANCELLED").contains(status) || dueOn == null
            || type.equals("SESSION_REMINDER") != (sessionId != null)) {
            throw ChildrenException.invalid("Choose a communication type and the required session.");
        }
    }
    public GuardianCommunication finish(long expected, String next, String reference, UUID actor, Instant now) {
        ChildrenRules.version(version, expected);
        if (!status.equals("DRAFT")) {
            throw ChildrenException.conflict("Only draft communications can be completed or cancelled.");
        }
        if (next == null || !List.of("CONTACTED", "CANCELLED").contains(next)) {
            throw ChildrenException.invalid("Record contact or cancel this draft.");
        }
        return new GuardianCommunication(id, childId, authorityId, guardianId, programmeId, policyId, sessionId, type,
            message, dueOn, next, createdBy, createdAt, actor, now, ChildrenRules.text(reference, 500), version + 1);
    }
    @Override
    public String toString() {
        return "GuardianCommunication[id=" + id + ", status=" + status + "]";
    }
}
