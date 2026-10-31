package org.onechurch.events.application.query.listeventattendance;
import java.util.UUID;
public record ListEventAttendanceQuery(UUID occurrenceId, String search, String status, int limit, int offset) {}
