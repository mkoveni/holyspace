package org.onechurch.events.presentation.api;
import org.onechurch.events.application.EventViews;
import org.onechurch.events.application.ScheduleReview;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.PutMapping;
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

import java.util.UUID;
@Tag(name = "Events")
public interface ChurchEventsApi {
    @Schema(name = "EventsCreated")
    record Created(UUID id) {}
    @Schema(name = "CreateChurchEventRequest")
    record CreateChurchEventRequest(@NotNull UUID requestId, String title, String type, String description,
        @NotNull ScheduleReview.Draft schedule, String previewHash) {}
    @PostMapping("/api/events")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Create Church Event", description = "Requires events.manage. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    Created createChurchEvent(@Valid @RequestBody CreateChurchEventRequest b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @Schema(name = "UpdateChurchEventRequest")
    record UpdateChurchEventRequest(@Min(0) long expectedVersion, String title, String type, String description,
        boolean active, @NotBlank @Size(max = 1000) String reason) {}
    @PutMapping("/api/events/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Update Church Event", description = "Requires events.manage. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    void updateChurchEvent(@PathVariable UUID id, @Valid @RequestBody UpdateChurchEventRequest b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @Schema(name = "CreateEventSeriesRequest")
    record CreateEventSeriesRequest(@NotNull UUID requestId, @Min(0) long expectedVersion,
        @NotNull ScheduleReview.Draft schedule, String previewHash) {}
    @PostMapping("/api/events/{id}/series")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Create Event Series", description = "Requires events.manage. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    Created createEventSeries(@PathVariable UUID id, @Valid @RequestBody CreateEventSeriesRequest b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @Schema(name = "PreviewEventScheduleRequest")
    record PreviewEventScheduleRequest(@NotNull ScheduleReview.Draft draft) {}
    @PostMapping("/api/events/schedule/preview")
    @Operation(summary = "Preview Event Schedule", description = "Requires events.read. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    ScheduleReview.Preview previewEventSchedule(@Valid @RequestBody PreviewEventScheduleRequest b);
    @GetMapping("/api/events")
    @Operation(summary = "List Church Events", description = "Requires events.read. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    EventViews.Events listChurchEvents(@RequestParam(required = false) String search,
        @RequestParam(defaultValue = "false") boolean includeArchived, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/events/{id}")
    @Operation(summary = "Get Church Event", description = "Requires events.read. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    EventViews.Details getChurchEvent(@PathVariable UUID id, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
}
