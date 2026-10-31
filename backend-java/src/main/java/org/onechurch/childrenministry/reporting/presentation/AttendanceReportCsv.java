package org.onechurch.childrenministry.reporting.presentation;
import org.onechurch.childrenministry.reporting.application.AttendanceReport;

import java.util.List;
import java.util.stream.Collectors;
final class AttendanceReportCsv {
    private AttendanceReportCsv() {
    }
    static String render(AttendanceReport report) {
        String header =
            "Child ID,Child,Programme,Class,Location,Room,Session ID,Attendance ID,Arrival UTC,Departure UTC,Location time zone,Outcome,Supervisor collection\r\n";
        return "\uFEFF" + header
            + report.visits()
                .stream()
                .map(visit -> List
                    .of(visit.childId().toString(), visit.childName(), visit.programme(), visit.ministryClass(),
                        visit.location(), visit.room(), visit.sessionId().toString(), visit.attendanceId().toString(),
                        visit.arrivedAt().toString(), visit.leftAt() == null ? "" : visit.leftAt().toString(),
                        visit.timeZone(), visit.outcome(), Boolean.toString(visit.supervisorCollection()))
                    .stream()
                    .map(AttendanceReportCsv::cell)
                    .collect(Collectors.joining(",")))
                .collect(Collectors.joining("\r\n", "", report.visits().isEmpty() ? "" : "\r\n"));
    }
    private static String cell(String value) {
        String clean = value.replace("\r", " ").replace("\n", " ");
        String leading = clean.stripLeading();
        if (!leading.isEmpty() && "=+-@".indexOf(leading.charAt(0)) >= 0) {
            clean = "'" + clean;
        }
        return "\"" + clean.replace("\"", "\"\"") + "\"";
    }
}
