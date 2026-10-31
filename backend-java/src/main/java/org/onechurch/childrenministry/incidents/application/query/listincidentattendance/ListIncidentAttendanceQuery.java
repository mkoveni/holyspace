package org.onechurch.childrenministry.incidents.application.query.listincidentattendance;
import java.util.UUID;
public record ListIncidentAttendanceQuery(UUID actorId, UUID sessionId, String search, int limit, int offset) {}
