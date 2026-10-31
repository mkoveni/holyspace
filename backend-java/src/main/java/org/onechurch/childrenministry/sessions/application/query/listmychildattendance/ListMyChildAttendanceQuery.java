package org.onechurch.childrenministry.sessions.application.query.listmychildattendance;
import java.util.UUID;
public record ListMyChildAttendanceQuery(UUID childId, int limit, int offset) {}
