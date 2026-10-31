package org.onechurch.childrenministry.reporting.presentation;
import org.onechurch.childrenministry.reporting.application.AttendanceReport;
import org.onechurch.childrenministry.reporting.application.AttendanceReportFilter;
import org.onechurch.childrenministry.reporting.application.query.exportattendancereport.ExportAttendanceReportHandler;
import org.onechurch.childrenministry.reporting.application.query.exportattendancereport.ExportAttendanceReportQuery;
import org.onechurch.childrenministry.reporting.application.query.getattendancereport.GetAttendanceReportHandler;
import org.onechurch.childrenministry.reporting.application.query.getattendancereport.GetAttendanceReportQuery;
import org.onechurch.childrenministry.reporting.application.query.getattendancetimeline.GetAttendanceTimelineHandler;
import org.onechurch.childrenministry.reporting.application.query.getattendancetimeline.GetAttendanceTimelineQuery;
import org.onechurch.childrenministry.reporting.presentation.api.AttendanceReportingApi;
import org.springframework.http.HttpHeaders;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.web.bind.annotation.RestController;

import java.time.LocalDate;
import java.util.UUID;
@RestController
public class AttendanceReportingController implements AttendanceReportingApi {
    private final GetAttendanceReportHandler report;
    private final ExportAttendanceReportHandler export;
    private final GetAttendanceTimelineHandler timeline;
    public AttendanceReportingController(GetAttendanceReportHandler report, ExportAttendanceReportHandler export,
        GetAttendanceTimelineHandler timeline) {
        this.report = report;
        this.export = export;
        this.timeline = timeline;
    }
    @Override
    @PreAuthorize("hasAuthority('children.read')")
    public AttendanceReport report(LocalDate from, LocalDate until, UUID programmeId, UUID locationId, UUID childId,
        String search, String outcome, int limit, int offset) {
        return report.handle(new GetAttendanceReportQuery(
            new AttendanceReportFilter(from, until, programmeId, locationId, childId, search, outcome), limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('children.read') and hasAuthority('children.manage')")
    public ResponseEntity<String> export(LocalDate from, LocalDate until, UUID programmeId, UUID locationId,
        UUID childId, String search, String outcome) {
        AttendanceReport result = export.handle(new ExportAttendanceReportQuery(
            new AttendanceReportFilter(from, until, programmeId, locationId, childId, search, outcome)));
        return ResponseEntity.ok()
            .header(HttpHeaders.CONTENT_DISPOSITION, "attachment; filename=\"children-attendance.csv\"")
            .header(HttpHeaders.CACHE_CONTROL, "no-store")
            .body(AttendanceReportCsv.render(result));
    }
    @Override
    @PreAuthorize("hasAuthority('children.read')")
    public AttendanceReport timeline(UUID attendanceId, int limit, int offset) {
        return timeline.handle(new GetAttendanceTimelineQuery(attendanceId, limit, offset));
    }
}
