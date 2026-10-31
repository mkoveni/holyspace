package org.onechurch.events.presentation.api;
import org.onechurch.events.application.EventReports;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RequestParam;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.media.Schema;
import io.swagger.v3.oas.annotations.tags.Tag;

import java.time.Instant;
import java.util.UUID;
@Tag(name = "EventAttendanceReports")
public interface EventAttendanceReportsApi {
    @Schema(name = "EventDayCreated")
    record Created(UUID id) {}
    @GetMapping("/api/events/attendance-report")

    @Operation(summary = "Get Event Attendance Report", description = "Requires events.attendance.read. Attendance is independent of bookings. Notice approval freezes reviewed content and dispatch rechecks eligibility.")
    EventReports.Page getEventAttendanceReport(@RequestParam(required = false) UUID eventId,
        @RequestParam(required = false) String search, @RequestParam() Instant from, @RequestParam() Instant until,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
    @GetMapping(value = "/api/events/attendance-report.csv", produces = "text/csv")

    @Operation(summary = "Export Event Attendance Report", description = "Requires events.attendance.read. Attendance is independent of bookings. Notice approval freezes reviewed content and dispatch rechecks eligibility.")
    ResponseEntity<String> exportEventAttendanceReport(@RequestParam(required = false) UUID eventId,
        @RequestParam(required = false) String search, @RequestParam() Instant from, @RequestParam() Instant until);
}
