package org.onechurch.childrenministry.sessions.application.query.getattendanceevents;

import java.util.UUID;
public record GetAttendanceEventsQuery(UUID attendanceId, int limit, int offset) {}
