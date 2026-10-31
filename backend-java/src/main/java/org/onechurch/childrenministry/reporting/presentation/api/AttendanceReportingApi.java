package org.onechurch.childrenministry.reporting.presentation.api;
import org.onechurch.childrenministry.reporting.application.AttendanceReport;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RequestParam;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;

import java.time.LocalDate;
import java.util.UUID;
@Tag(name = "Children Attendance Reporting")
public interface AttendanceReportingApi {
    @GetMapping("/api/children/attendance-reports")
    @Operation(summary = "Report attendance across sessions", description = "Requires children.read. Inclusive dates use the church time zone, filter visit arrivals, and cover at most 366 days. Totals cover the complete filtered result before pagination. Each room visit is one row; transferred visits remain in source sessions. Outcomes: ALL, ATTENDING, COLLECTED, TRANSFERRED, SUPERVISOR. No codes, care, collector identity or exception reasons are exposed.")
    AttendanceReport report(@RequestParam LocalDate from, @RequestParam LocalDate until,
        @RequestParam(required = false) UUID programmeId, @RequestParam(required = false) UUID locationId,
        @RequestParam(required = false) UUID childId, @RequestParam(defaultValue = "") String search,
        @RequestParam(defaultValue = "ALL") String outcome, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @GetMapping(value = "/api/children/attendance-reports/export", produces = "text/csv")
    @Operation(summary = "Export filtered attendance CSV", description = "Requires both children.read and children.manage. Same filters as report. Exports all matching visits up to 10,000; larger exports are rejected without truncation. Times are ISO UTC with a location time-zone column. Spreadsheet formula text is escaped.")
    ResponseEntity<String> export(@RequestParam LocalDate from, @RequestParam LocalDate until,
        @RequestParam(required = false) UUID programmeId, @RequestParam(required = false) UUID locationId,
        @RequestParam(required = false) UUID childId, @RequestParam(defaultValue = "") String search,
        @RequestParam(defaultValue = "ALL") String outcome);
    @GetMapping("/api/children/attendance/{attendanceId}/timeline")
    @Operation(summary = "Read the complete attendance journey", description = "Requires children.read. Returns chronological room visits with programme, location, arrival, departure, transfer and supervisor collection indicators, independently of report dates. Paginated, with complete visit totals. No private evidence or pickup codes.")
    AttendanceReport timeline(@PathVariable UUID attendanceId, @RequestParam(defaultValue = "50") int limit,
        @RequestParam(defaultValue = "0") int offset);
}
