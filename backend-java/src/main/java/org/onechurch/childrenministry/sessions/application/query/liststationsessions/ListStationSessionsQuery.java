package org.onechurch.childrenministry.sessions.application.query.liststationsessions;
import java.time.Instant;
import java.util.UUID;
public record ListStationSessionsQuery(UUID actorId, Instant from, Instant until, int limit, int offset) {}
