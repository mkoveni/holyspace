package org.onechurch.events.presentation.api;
import org.onechurch.events.application.EventAttendanceOperations;
import org.onechurch.events.application.contract.AttendanceReader;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.media.Schema;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.util.List;
import java.util.UUID;
@Tag(name = "EventAttendance")
public interface EventAttendanceApi {
    @Schema(name = "EventDayCreated")
    record Created(UUID id) {}
    @Schema(name = "CheckInEventPersonBody")
    record CheckInEventPersonBody(@NotNull UUID requestId, @Min(0) long expectedVersion) {}
    @PostMapping("/api/events/registrations/{id}/check-in")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Check In Event Person", description = "Requires events.attendance.manage. Attendance is independent of bookings. Notice approval freezes reviewed content and dispatch rechecks eligibility.")
    Created checkInEventPerson(@PathVariable UUID id, @Valid @RequestBody CheckInEventPersonBody b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @Schema(name = "RegisterEventWalkInBody")
    record RegisterEventWalkInBody(@NotNull UUID requestId, @NotNull UUID personId, @Min(0) Long expectedVersion) {}
    @PostMapping("/api/events/occurrences/{id}/walk-ins")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Register Event Walk In", description = "Requires events.attendance.manage. Attendance is independent of bookings. Notice approval freezes reviewed content and dispatch rechecks eligibility.")
    EventAttendanceOperations.WalkIn registerEventWalkIn(@PathVariable UUID id,
        @Valid @RequestBody RegisterEventWalkInBody b, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @Schema(name = "CorrectEventAttendanceBody")
    record CorrectEventAttendanceBody(@Min(0) long expectedVersion, @NotBlank String status,
        @NotBlank @Size(max = 1000) String reason) {}
    @PostMapping("/api/events/registrations/{id}/attendance-correction")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Correct Event Attendance", description = "Requires events.attendance.manage. Attendance is independent of bookings. Notice approval freezes reviewed content and dispatch rechecks eligibility.")
    void correctEventAttendance(@PathVariable UUID id, @Valid @RequestBody CorrectEventAttendanceBody b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @GetMapping("/api/events/occurrences/{id}/attendance")

    @Operation(summary = "List Event Attendance", description = "Requires events.attendance.read. Attendance is independent of bookings. Notice approval freezes reviewed content and dispatch rechecks eligibility.")
    AttendanceReader.Page listEventAttendance(@PathVariable UUID id, @RequestParam(required = false) String search,
        @RequestParam(required = false) String status, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/events/registrations/{id}/attendance-history")

    @Operation(summary = "Get Event Attendance History", description = "Requires events.attendance.read. Attendance is independent of bookings. Notice approval freezes reviewed content and dispatch rechecks eligibility.")
    List<AttendanceReader.History> getEventAttendanceHistory(@PathVariable UUID id,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
}
