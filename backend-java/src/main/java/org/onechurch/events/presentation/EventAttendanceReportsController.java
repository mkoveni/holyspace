package org.onechurch.events.presentation;
import org.onechurch.events.application.EventReports;
import org.onechurch.events.application.query.exporteventattendancereport.ExportEventAttendanceReportHandler;
import org.onechurch.events.application.query.exporteventattendancereport.ExportEventAttendanceReportQuery;
import org.onechurch.events.application.query.geteventattendancereport.GetEventAttendanceReportHandler;
import org.onechurch.events.application.query.geteventattendancereport.GetEventAttendanceReportQuery;
import org.onechurch.events.presentation.api.EventAttendanceReportsApi;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.RestController;

import java.time.Instant;
import java.util.UUID;
@RestController
public class EventAttendanceReportsController implements EventAttendanceReportsApi {
    private final GetEventAttendanceReportHandler getEventAttendanceReport;
    private final ExportEventAttendanceReportHandler exportEventAttendanceReport;
    public EventAttendanceReportsController(GetEventAttendanceReportHandler getEventAttendanceReport,
        ExportEventAttendanceReportHandler exportEventAttendanceReport) {
        this.getEventAttendanceReport = getEventAttendanceReport;
        this.exportEventAttendanceReport = exportEventAttendanceReport;
    }
    @Override
    @PreAuthorize("hasAuthority('events.attendance.read')")
    public EventReports.Page getEventAttendanceReport(UUID eventId, String search, Instant from, Instant until,
        int limit, int offset) {
        return getEventAttendanceReport
            .handle(new GetEventAttendanceReportQuery(eventId, search, from, until, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('events.attendance.read')")
    public ResponseEntity<String> exportEventAttendanceReport(UUID eventId, String search, Instant from,
        Instant until) {
        return ResponseEntity.ok()
            .contentType(MediaType.parseMediaType("text/csv;charset=UTF-8"))
            .header("Content-Disposition", "attachment; filename=event-attendance.csv")
            .body(
                exportEventAttendanceReport.handle(new ExportEventAttendanceReportQuery(eventId, search, from, until)));
    }
}
