package org.onechurch.childrenministry.incidents.application.command.reportincident;
import java.time.Instant;
import java.time.LocalDate;
import java.util.UUID;
public record ReportIncidentCommand(UUID actorId, UUID requestId, UUID childId, UUID sessionId, UUID segmentId,
    String category, String severity, Instant occurredAt, String description, String immediateAction,
    LocalDate reviewOn, LocalDate retainUntil) {
    @Override
    public String toString() {
        return "ReportIncidentCommand[restricted]";
    }
}
