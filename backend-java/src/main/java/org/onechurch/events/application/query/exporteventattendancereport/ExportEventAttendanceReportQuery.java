package org.onechurch.events.application.query.exporteventattendancereport;
import java.time.Instant;
import java.util.UUID;
public record ExportEventAttendanceReportQuery(UUID eventId, String search, Instant from, Instant until) {}
