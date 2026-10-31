package org.onechurch.events.presentation.api;
import org.onechurch.events.application.MemberEventActions;
import org.onechurch.events.application.contract.MemberEventReader;
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
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.util.UUID;
@Tag(name = "Member events")
public interface MemberEventsApi {
    record Created(UUID id) {}
    record Register(@NotNull UUID requestId, @Min(0) Long expectedVersion) {}
    record Cancel(@Min(0) long expectedVersion) {}
    record Publish(boolean enabled, @Min(0) long expectedVersion) {}
    record Scan(@NotNull UUID requestId, @NotBlank @Size(max = 250) String credential) {
        @Override
        public String toString() {
            return "Scan[credential=redacted]";
        }
    }
    @GetMapping("/api/member/events")
    @Operation(summary = "Browse member events or own upcoming bookings", description = "Verified member identity required. Catalogue is explicitly published; mine includes own withdrawn or cancelled bookings. No rosters or staff notes. Upcoming 180 days, bounded pagination.")
    MemberEventReader.Page list(@RequestParam(defaultValue = "false") boolean mine,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/member/events/{id}")
    @Operation(summary = "View a published gathering or own booking")
    MemberEventReader.Item detail(@PathVariable UUID id);
    @PostMapping("/api/member/events/{id}/registration")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Register the current member", description = "Identity is derived on the server. Stable request IDs prevent duplicates. Capacity, FIFO waitlist and versioned re-registration use existing event rules.")
    Created register(@PathVariable UUID id, @Valid @RequestBody Register body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @PostMapping("/api/member/events/{id}/cancel")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Cancel the current member's booking")
    void cancel(@PathVariable UUID id, @Valid @RequestBody Cancel body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @PostMapping("/api/member/events/{id}/attendance-pass")
    @Operation(summary = "Issue a five-minute event attendance QR credential", description = "Confirmed booking and open admission window required. Not a guardian or child collection credential. Bound to member identity, live session, booking and schedule revision.")
    MemberEventActions.Pass pass(@PathVariable UUID id,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @PostMapping("/api/events/occurrences/{id}/scan-member")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Record a member QR arrival", description = "Requires events.attendance.manage. Single-use credential, selected occurrence, current identity/session/booking and admission window are validated under locks.")
    Created scan(@PathVariable UUID id, @Valid @RequestBody Scan body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @GetMapping("/api/events/{id}/member-offer")
    @Operation(summary = "Read member catalogue publication", description = "Requires events.manage.")
    MemberEventReader.Offer offer(@PathVariable UUID id);
    @PutMapping("/api/events/{id}/member-offer")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Publish or withdraw an event from the member catalogue", description = "Requires events.manage. Existing own bookings remain visible and cancellable.")
    void publish(@PathVariable UUID id, @Valid @RequestBody Publish body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
}
