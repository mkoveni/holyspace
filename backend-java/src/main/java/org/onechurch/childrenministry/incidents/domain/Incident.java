package org.onechurch.childrenministry.incidents.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;

import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
public record Incident(UUID id, UUID childId, UUID sessionId, UUID segmentId, String category, String severity,
    Instant occurredAt, UUID reportedBy, Instant reportedAt, String status, UUID ownerId, LocalDate reviewOn,
    LocalDate retainUntil, long version) {
    public Incident {
        occurredAt = ChildrenRules.instant(occurredAt);
        reportedAt = ChildrenRules.instant(reportedAt);
        if (id == null || childId == null || sessionId == null || segmentId == null || reportedBy == null
            || occurredAt == null || reportedAt == null || occurredAt.isAfter(reportedAt) || category == null
            || !List.of("INJURY", "ILLNESS", "BEHAVIOUR", "SAFEGUARDING", "OTHER").contains(category)
            || severity == null || !List.of("LOW", "MODERATE", "HIGH", "CRITICAL").contains(severity) || status == null
            || !List.of("OPEN", "IN_REVIEW", "CLOSED").contains(status) || (!status.equals("OPEN") && ownerId == null)
            || reviewOn == null || retainUntil == null || retainUntil.isBefore(reviewOn) || version < 0) {
            throw ChildrenException
                .invalid("Choose a valid incident category, severity, review date and retention date.");
        }
    }
    public Incident triage(long expected, UUID owner, LocalDate review, LocalDate on) {
        ChildrenRules.version(version, expected);
        requireOpen();
        if (owner == null || review == null || review.isBefore(on)) {
            throw ChildrenException.invalid("Assign responsibility and a current or future review date.");
        }
        return new Incident(id, childId, sessionId, segmentId, category, severity, occurredAt, reportedBy, reportedAt,
            "IN_REVIEW", owner, review, retainUntil, version + 1);
    }
    public Incident progress(long expected, String action, LocalDate on) {
        ChildrenRules.version(version, expected);
        if (action == null || !List.of("ACTION", "GUARDIAN_CONTACT", "CLOSED", "REOPENED").contains(action)) {
            throw ChildrenException.invalid("Choose a valid follow-up action.");
        }
        if (action.equals("REOPENED")) {
            if (!status.equals("CLOSED")) {
                throw ChildrenException.conflict("Only closed incidents can be reopened.");
            }
            return new Incident(id, childId, sessionId, segmentId, category, severity, occurredAt, reportedBy,
                reportedAt, "IN_REVIEW", ownerId, on, retainUntil, version + 1);
        }
        requireOpen();
        if (action.equals("CLOSED") && ownerId == null) {
            throw ChildrenException.conflict("Assign responsibility before closing this incident.");
        }
        return new Incident(id, childId, sessionId, segmentId, category, severity, occurredAt, reportedBy, reportedAt,
            action.equals("CLOSED") ? "CLOSED" : status, ownerId, reviewOn, retainUntil, version + 1);
    }
    private void requireOpen() {
        if (status.equals("CLOSED")) {
            throw ChildrenException.conflict("Reopen this incident before recording further follow-up.");
        }
    }
}
