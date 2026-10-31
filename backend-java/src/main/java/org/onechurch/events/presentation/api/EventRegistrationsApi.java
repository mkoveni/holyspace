package org.onechurch.events.presentation.api;
import org.onechurch.events.application.EventViews;
import org.onechurch.events.application.contract.EventJournalReader;
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

import java.util.List;
import java.util.UUID;
@Tag(name = "Event registrations")
public interface EventRegistrationsApi {
    @Schema(name = "EventsCreated")
    record Created(UUID id) {}
    @Schema(name = "ConfigureEventRegistrationRequest")
    record ConfigureEventRegistrationRequest(@Min(0) long expectedVersion, boolean open, Integer capacity,
        boolean waitlistEnabled, @NotBlank @Size(max = 1000) String reason) {}
    @PutMapping("/api/events/occurrences/{id}/registration")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Configure Event Registration", description = "Requires events.registrations.manage. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    void configureEventRegistration(@PathVariable UUID id, @Valid @RequestBody ConfigureEventRegistrationRequest b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @Schema(name = "RegisterEventPersonRequest")
    record RegisterEventPersonRequest(@NotNull UUID requestId, @NotNull UUID personId, @Min(0) Long expectedVersion) {}
    @PostMapping("/api/events/occurrences/{id}/registrations")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Register Event Person", description = "Requires events.registrations.manage. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    Created registerEventPerson(@PathVariable UUID id, @Valid @RequestBody RegisterEventPersonRequest b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @Schema(name = "CancelEventRegistrationRequest")
    record CancelEventRegistrationRequest(@Min(0) long expectedVersion, @NotBlank @Size(max = 1000) String reason) {}
    @PostMapping("/api/events/registrations/{id}/cancel")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Cancel Event Registration", description = "Requires events.registrations.manage. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    void cancelEventRegistration(@PathVariable UUID id, @Valid @RequestBody CancelEventRegistrationRequest b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @GetMapping("/api/events/occurrences/{id}/registrations")
    @Operation(summary = "List Event Registrations", description = "Requires events.registrations.read. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    EventViews.Registrations listEventRegistrations(@PathVariable UUID id,
        @RequestParam(required = false) String status, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/events/registrations/{id}/history")
    @Operation(summary = "Get Event Registration History", description = "Requires events.registrations.read. Preview hashes and optimistic versions protect against stale changes. Occurrences retain stable identities.")
    List<EventJournalReader.RegistrationEntry> getEventRegistrationHistory(@PathVariable UUID id,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
}
