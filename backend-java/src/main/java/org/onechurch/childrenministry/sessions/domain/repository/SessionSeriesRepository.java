package org.onechurch.childrenministry.sessions.domain.repository;
import org.onechurch.childrenministry.sessions.domain.SessionSeries;

import java.time.LocalDate;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface SessionSeriesRepository {
    void lockScheduling();
    Optional<SessionSeries> request(UUID actorId, UUID requestId);
    Optional<SessionSeries> forUpdate(UUID id);
    void insert(SessionSeries series);
    void update(SessionSeries series);
    void link(UUID seriesId, UUID sessionId, LocalDate date);
    List<UUID> sessionIds(UUID seriesId);
}
