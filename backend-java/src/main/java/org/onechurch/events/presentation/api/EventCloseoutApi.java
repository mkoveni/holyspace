package org.onechurch.events.presentation.api;
import org.onechurch.events.application.EventReadiness;
import org.onechurch.events.application.contract.CloseoutReader;
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
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Size;

import java.util.List;
import java.util.UUID;
@Tag(name = "EventOperations")
public interface EventCloseoutApi {
    record CloseBody(@Min(0) long expectedVersion, @NotBlank String reviewHash, @NotBlank @Size(max = 2000) String note,
        @Size(max = 2000) String followUp, boolean attendanceReviewed, boolean followUpReviewed) {}
    record ReopenBody(@Min(0) long expectedVersion, @NotBlank @Size(max = 2000) String reason) {}
    @GetMapping("/api/events/occurrences/{id}/readiness")
    @Operation(summary = "Review event readiness and closeout", description = "Requires events.manage. Read-only live checklist, attendance totals and scoped notice outcomes. Staffing totals are current assessments, not historical evidence.")
    EventReadiness.View readiness(@PathVariable UUID id);
    @PostMapping("/api/events/occurrences/{id}/closeout")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Close out a finished or cancelled occurrence", description = "Requires events.manage. Supply a fresh readiness hash, current closeout version, both review acknowledgements, a closeout note and follow-up when waitlists or email issues remain. Preserves attendance and booking history.")
    void close(@PathVariable UUID id, @Valid @RequestBody CloseBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @PostMapping("/api/events/occurrences/{id}/closeout/reopen")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Reopen a closeout for review", description = "Requires events.manage and a reason. Creates a new version without deleting previous closeout evidence. Attendance corrections remain available independently and are reported as changes since closeout.")
    void reopen(@PathVariable UUID id, @Valid @RequestBody ReopenBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @GetMapping("/api/events/occurrences/{id}/closeout/history")
    @Operation(summary = "Read closeout history", description = "Requires events.manage. Includes immutable reviewed totals, actor, note and follow-up for every close/reopen action.")
    List<CloseoutReader.History> history(@PathVariable UUID id, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
}
