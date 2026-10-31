package org.onechurch.events.presentation.api;
import org.onechurch.events.application.EventViews;
import org.onechurch.events.application.OccurrenceChangeReview;
import org.onechurch.events.application.contract.EventJournalReader;
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
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.time.Instant;
import java.util.List;
import java.util.UUID;
@Tag(name = "Event occurrences")
public interface EventOccurrencesApi {
    @Schema(name = "EventsCreated")
    record Created(UUID id) {}
    @Schema(name = "RescheduleEventOccurrencesRequest")
    record RescheduleEventOccurrencesRequest(@NotNull OccurrenceChangeReview.Draft draft, String reviewHash,
        @NotBlank @Size(max = 1000) String reason) {}
    @PostMapping("/api/events/occurrences/reschedule")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Reschedule Event Occurrences", description = "Requires events.manage. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    void rescheduleEventOccurrences(@Valid @RequestBody RescheduleEventOccurrencesRequest b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @Schema(name = "CancelEventOccurrencesRequest")
    record CancelEventOccurrencesRequest(@NotNull OccurrenceChangeReview.Draft draft, String reviewHash,
        @NotBlank @Size(max = 1000) String reason) {}
    @PostMapping("/api/events/occurrences/cancel")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Cancel Event Occurrences", description = "Requires events.manage. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    void cancelEventOccurrences(@Valid @RequestBody CancelEventOccurrencesRequest b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @Schema(name = "RestoreEventOccurrenceRequest")
    record RestoreEventOccurrenceRequest(@NotNull OccurrenceChangeReview.Draft draft, String reviewHash,
        @NotBlank @Size(max = 1000) String reason) {}
    @PostMapping("/api/events/occurrences/restore")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Restore Event Occurrence", description = "Requires events.manage. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    void restoreEventOccurrence(@Valid @RequestBody RestoreEventOccurrenceRequest b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @Schema(name = "PreviewEventOccurrenceChangeRequest")
    record PreviewEventOccurrenceChangeRequest(@NotNull OccurrenceChangeReview.Draft draft) {}
    @PostMapping("/api/events/occurrences/preview")
    @Operation(summary = "Preview Event Occurrence Change", description = "Requires events.read. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    OccurrenceChangeReview.Preview previewEventOccurrenceChange(
        @Valid @RequestBody PreviewEventOccurrenceChangeRequest b);
    @GetMapping("/api/events/calendar")
    @Operation(summary = "List Event Calendar", description = "Requires events.read. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    EventViews.Calendar listEventCalendar(@RequestParam(required = false) UUID eventId,
        @RequestParam(required = false) String search, @RequestParam Instant from, @RequestParam Instant until,
        @RequestParam(required = false) String status, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/events/occurrences/{id}")
    @Operation(summary = "Get Event Occurrence", description = "Requires events.read. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    EventViews.Occurrence getEventOccurrence(@PathVariable UUID id);
    @GetMapping("/api/events/occurrences/{id}/history")
    @Operation(summary = "Get Event Occurrence History", description = "Requires events.read. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    List<EventJournalReader.OccurrenceEntry> getEventOccurrenceHistory(@PathVariable UUID id,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
}
