package org.onechurch.childrenministry.incidents.application.query.listincidents;
import java.util.UUID;
public record ListIncidentsQuery(UUID sessionId, UUID childId, String status, boolean overdueOnly, int limit,
    int offset) {}
