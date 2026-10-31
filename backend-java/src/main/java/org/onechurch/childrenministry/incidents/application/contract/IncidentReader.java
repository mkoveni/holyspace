package org.onechurch.childrenministry.incidents.application.contract;
import org.onechurch.childrenministry.incidents.domain.Incident;
import org.onechurch.childrenministry.incidents.domain.IncidentDetails;
import org.onechurch.childrenministry.incidents.domain.IncidentEvent;

import java.time.Instant;
import java.time.LocalDate;
import java.util.Collection;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface IncidentReader {
    record Receipt(UUID id, String requestHash) {}
    record Page(List<Incident> incidents, long total, long overdue, long unassigned) {}
    record Counts(long unresolved, long overdue, long unassigned) {}
    record Visit(UUID segmentId, UUID childId, Instant arrivedAt, Instant leftAt) {}
    record Visits(List<Visit> visits, long total) {}
    Visits visits(UUID sessionId, Collection<UUID> matchingIds, int limit, int offset);
    Optional<Receipt> receipt(UUID actorId, UUID requestId);
    Optional<Incident> find(UUID id);
    Optional<IncidentDetails> details(UUID id);
    List<IncidentEvent> events(UUID id, int limit, int offset);
    long eventCount(UUID id);
    boolean attended(UUID childId, UUID sessionId, UUID segmentId, Instant at);
    Page list(UUID sessionId, UUID childId, String status, boolean overdueOnly, LocalDate on, int limit, int offset);
    Counts counts(UUID sessionId, LocalDate on);
}
