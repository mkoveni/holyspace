package org.onechurch.childrenministry.reporting.application.query.getattendancetimeline;
import java.util.UUID;
public record GetAttendanceTimelineQuery(UUID attendanceId, int limit, int offset) {}
