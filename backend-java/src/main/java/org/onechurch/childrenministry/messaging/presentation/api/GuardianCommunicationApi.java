package org.onechurch.childrenministry.messaging.presentation.api;
import org.onechurch.childrenministry.messaging.application.query.listguardiancommunications.ListGuardianCommunicationsHandler;
import org.onechurch.childrenministry.messaging.domain.GuardianCommunication;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.time.LocalDate;
import java.util.UUID;
@RequestMapping("/api/children/guardian-communications")
@Tag(name = "Children guardian communications")
public interface GuardianCommunicationApi {
    record Created(UUID id) {}
    record Prepare(@NotNull UUID requestId, @NotNull UUID childId, @NotNull UUID authorityId, @NotNull UUID programmeId,
        UUID sessionId, @NotBlank String type, @NotNull LocalDate dueOn) {}
    record Contact(@Min(0) long expectedVersion, @NotBlank String status,
        @NotBlank @Size(max = 500) String reference) {}
    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Prepare a guardian message", description = "Requires children.safeguarding. CONSENT_RENEWAL uses the current policy and active enrolment; SESSION_REMINDER requires a future active session and class placement. Both require current adult verified guardian authority. Generates safe wording without incident/care content. Actor-scoped retry keys. Preparing a draft sends nothing. Email requires separate reviewed delivery approval; SMS is not configured.")
    Created prepare(@Valid @RequestBody Prepare body, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @GetMapping
    @Operation(summary = "List guardian communication tasks", description = "Requires children.safeguarding. Paged metadata, no message text. DRAFT/CONTACTED/CANCELLED statuses reflect preparation and manually recorded contact, never delivery confirmation.")
    ListGuardianCommunicationsHandler.View list(@RequestParam(required = false) UUID childId,
        @RequestParam(defaultValue = "DRAFT") String status, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/{id}")
    @Operation(summary = "Review a prepared guardian message", description = "Requires children.safeguarding. Live authority, policy and session eligibility are rechecked for draft messages before text is exposed. Read-only; no communication is sent.")
    GuardianCommunication get(@PathVariable UUID id);
    @PostMapping("/{id}/contact")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Record manual contact or cancel a draft", description = "Requires children.safeguarding. Optimistic version; CONTACTED rechecks guardian authority and current consent policy/session placement. Requires a contact reference or cancellation reason. Does not create consent, change authority, or confirm message delivery.")
    void contact(@PathVariable UUID id, @Valid @RequestBody Contact body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
}
