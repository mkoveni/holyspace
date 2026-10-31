package org.onechurch.events.application.query.geteventattendancehistory;
import java.util.UUID;
public record GetEventAttendanceHistoryQuery(UUID registrationId, int limit, int offset) {}
