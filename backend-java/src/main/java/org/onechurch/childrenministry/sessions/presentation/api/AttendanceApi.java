package org.onechurch.childrenministry.sessions.presentation.api;
import org.onechurch.childrenministry.sessions.application.AttendanceView;
import org.onechurch.childrenministry.sessions.application.LabelIssue;
import org.onechurch.childrenministry.sessions.application.query.getexpectedchildren.ExpectedChild;
import org.onechurch.childrenministry.sessions.application.query.getpickupcollectors.PickupCollector;
import org.onechurch.childrenministry.sessions.domain.AttendanceEvent;
import org.onechurch.childrenministry.sessions.domain.AttendanceSegment;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.media.Schema;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.util.List;
import java.util.UUID;
@Tag(name = "Children Attendance")
@RequestMapping("/api/children")
public interface AttendanceApi {
    @Schema(name = "ChildrenCheckInChildRequest")
    record CheckInChildRequest(@NotNull UUID sessionId, @NotNull UUID childId, @NotNull UUID requestId) {}
    @PostMapping("/attendance")
    @Operation(summary = "Check In Child", description = "Requires children.station. Version conflicts require reloading the current resource.")
    AttendanceView checkInChild(@Valid @RequestBody CheckInChildRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenIssuePickupLabelRequest")
    record IssuePickupLabelRequest(@NotNull UUID attendanceId, @Min(0) long expectedVersion,
        @Size(max = 2000) String replacementReason) {}
    @PostMapping("/attendance/labels")
    @Operation(summary = "Issue Pickup Label", description = "Requires children.station. Version conflicts require reloading the current resource.")
    LabelIssue issuePickupLabel(@Valid @RequestBody IssuePickupLabelRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenCheckOutChildRequest")
    record CheckOutChildRequest(@NotNull UUID attendanceId, @Min(0) long expectedVersion, @NotNull UUID requestId,
        @NotNull UUID collectorId, boolean identityVerified,
        @Size(max = 2000) @Schema(accessMode = Schema.AccessMode.WRITE_ONLY) String pickupCode) {
        @Override
        public String toString() {
            return "CheckOutChildRequest[redacted]";
        }
    }
    @PostMapping("/attendance/checkout")
    @Operation(summary = "Check Out Child", description = "Requires children.station. Version conflicts require reloading the current resource.")
    AttendanceView checkOutChild(@Valid @RequestBody CheckOutChildRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenSupervisorCheckoutRequest")
    record SupervisorCheckoutRequest(@NotNull UUID attendanceId, @Min(0) long expectedVersion, @NotNull UUID requestId,
        @NotNull UUID collectorId, boolean identityVerified, @Size(max = 2000) String reason) {}
    @PostMapping("/attendance/supervisor-checkout")
    @Operation(summary = "Supervisor Checkout", description = "Requires children.supervise. Version conflicts require reloading the current resource.")
    AttendanceView supervisorCheckout(@Valid @RequestBody SupervisorCheckoutRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenTransferChildRequest")
    record TransferChildRequest(@NotNull UUID attendanceId, @Min(0) long expectedVersion, @NotNull UUID targetSessionId,
        @Size(max = 2000) String reason) {}
    @PostMapping("/attendance/transfers")
    @Operation(summary = "Transfer Child", description = "Requires children.supervise. Version conflicts require reloading the current resource.")
    AttendanceView transferChild(@Valid @RequestBody TransferChildRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @GetMapping("/sessions/{sessionId}/roster")
    @Operation(summary = "Get Roster", description = "Requires children.station. Version conflicts require reloading the current resource.")
    List<AttendanceView> getRoster(@PathVariable("sessionId") UUID sessionId,
        @RequestParam(name = "openOnly", defaultValue = "true") boolean openOnly,
        @RequestParam(name = "limit", defaultValue = "50") int limit,
        @RequestParam(name = "offset", defaultValue = "0") int offset,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @GetMapping("/attendance/children/{childId}")
    @Operation(summary = "Get Child Attendance", description = "Requires children.read. Version conflicts require reloading the current resource.")
    List<AttendanceView> getChildAttendance(@PathVariable("childId") UUID childId,
        @RequestParam(name = "limit", defaultValue = "50") int limit,
        @RequestParam(name = "offset", defaultValue = "0") int offset);
    @GetMapping("/attendance/{attendanceId}/events")
    @Operation(summary = "Get Attendance Events", description = "Requires children.supervise. Version conflicts require reloading the current resource.")
    List<AttendanceEvent> getAttendanceEvents(@PathVariable("attendanceId") UUID attendanceId,
        @RequestParam(name = "limit", defaultValue = "50") int limit,
        @RequestParam(name = "offset", defaultValue = "0") int offset);
    @GetMapping("/attendance/{attendanceId}/segments")
    @Operation(summary = "Get Attendance Segments", description = "Requires children.read. Version conflicts require reloading the current resource.")
    List<AttendanceSegment> getAttendanceSegments(@PathVariable("attendanceId") UUID attendanceId);
    @GetMapping("/attendance/{attendanceId}")
    @Operation(summary = "Get Attendance", description = "Requires children.station. Version conflicts require reloading the current resource.")
    AttendanceView getAttendance(@PathVariable("attendanceId") UUID attendanceId,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);

    @GetMapping("/sessions/{sessionId}/expected-children")
    @Operation(summary = "List expected children", description = "Assigned station staff only. Enrolment does not reserve session capacity.")
    List<ExpectedChild> getExpectedChildren(@PathVariable("sessionId") UUID sessionId,
        @RequestParam(name = "limit", defaultValue = "50") int limit,
        @RequestParam(name = "offset", defaultValue = "0") int offset,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @GetMapping("/attendance/{attendanceId}/collectors")
    @Operation(summary = "List eligible pickup collectors", description = "Returns current collector names and grant IDs without guardian evidence. Checkout revalidates authorization.")
    List<PickupCollector> getPickupCollectors(@PathVariable("attendanceId") UUID attendanceId,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
}
