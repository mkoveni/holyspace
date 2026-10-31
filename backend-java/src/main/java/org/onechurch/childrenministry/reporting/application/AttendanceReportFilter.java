package org.onechurch.childrenministry.reporting.application;
import java.time.LocalDate;
import java.util.UUID;
public record AttendanceReportFilter(LocalDate from, LocalDate until, UUID programmeId, UUID locationId, UUID childId,
    String search, String outcome) {}
