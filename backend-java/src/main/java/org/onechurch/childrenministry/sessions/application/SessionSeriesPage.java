package org.onechurch.childrenministry.sessions.application;
import org.onechurch.childrenministry.sessions.domain.SessionStatus;

import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
public record SessionSeriesPage(List<Summary> series, long total) {
    public record Summary(UUID id, String name, UUID sourceSessionId, String className, String roomName,
        String timeZone, LocalDate firstDate, int intervalWeeks, int occurrences, long totalSessions,
        long plannedSessions, long cancelledSessions, boolean stopped, long version) {}
    public record ScheduledSession(UUID id, LocalDate date, Instant startsAt, Instant endsAt, SessionStatus status) {}
    public record Detail(Summary series, List<ScheduledSession> sessions) {}
}
