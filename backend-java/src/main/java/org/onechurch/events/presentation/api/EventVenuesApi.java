package org.onechurch.events.presentation.api;
import org.onechurch.events.application.EventViews;
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

import java.util.UUID;
@Tag(name = "Event venues")
public interface EventVenuesApi {
    @Schema(name = "EventsCreated")
    record Created(UUID id) {}
    @Schema(name = "CreateEventVenueRequest")
    record CreateEventVenueRequest(String name, String address, boolean exclusive) {}
    @PostMapping("/api/events/venues")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Create Event Venue", description = "Requires events.manage. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    Created createEventVenue(@Valid @RequestBody CreateEventVenueRequest b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @Schema(name = "UpdateEventVenueRequest")
    record UpdateEventVenueRequest(@Min(0) long expectedVersion, String name, String address, boolean active) {}
    @PutMapping("/api/events/venues/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Update Event Venue", description = "Requires events.manage. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    void updateEventVenue(@PathVariable UUID id, @Valid @RequestBody UpdateEventVenueRequest b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @GetMapping("/api/events/venues")
    @Operation(summary = "List Event Venues", description = "Requires events.read. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    EventViews.Venues listEventVenues(@RequestParam(required = false) String search,
        @RequestParam(defaultValue = "false") boolean includeArchived, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
}
