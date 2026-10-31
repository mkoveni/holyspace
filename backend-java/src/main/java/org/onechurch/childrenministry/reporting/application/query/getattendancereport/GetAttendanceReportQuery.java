package org.onechurch.childrenministry.reporting.application.query.getattendancereport;
import org.onechurch.childrenministry.reporting.application.AttendanceReportFilter;
public record GetAttendanceReportQuery(AttendanceReportFilter filter, int limit, int offset) {}
