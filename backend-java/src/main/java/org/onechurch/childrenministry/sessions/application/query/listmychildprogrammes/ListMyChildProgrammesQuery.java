package org.onechurch.childrenministry.sessions.application.query.listmychildprogrammes;
import java.util.UUID;
public record ListMyChildProgrammesQuery(UUID childId, int limit, int offset) {}
