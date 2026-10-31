package org.onechurch.events.application.query.geteventattendancereport;
import java.time.Instant;
import java.util.UUID;
public record GetEventAttendanceReportQuery(UUID eventId, String search, Instant from, Instant until, int limit,
    int offset) {}
