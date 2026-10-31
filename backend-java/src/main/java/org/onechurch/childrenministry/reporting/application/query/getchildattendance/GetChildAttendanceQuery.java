package org.onechurch.childrenministry.reporting.application.query.getchildattendance;

import java.util.UUID;
public record GetChildAttendanceQuery(UUID childId, int limit, int offset) {}
