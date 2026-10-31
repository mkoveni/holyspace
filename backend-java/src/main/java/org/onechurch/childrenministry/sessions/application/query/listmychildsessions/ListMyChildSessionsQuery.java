package org.onechurch.childrenministry.sessions.application.query.listmychildsessions;
import java.util.UUID;
public record ListMyChildSessionsQuery(UUID childId, int limit, int offset) {}
