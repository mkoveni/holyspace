package org.onechurch.childrenministry.sessions.application.contract;
import org.onechurch.childrenministry.sessions.application.SessionSeriesPage;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface SessionSeriesReader {
    boolean conflicts(UUID classId, UUID roomId, Instant from, Instant until);
    SessionSeriesPage list(int limit, int offset);
    Optional<SessionSeriesPage.Summary> summary(UUID id);
    List<SessionSeriesPage.ScheduledSession> sessions(UUID id);
}
