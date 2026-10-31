package org.onechurch.volunteering.presentation.api;

import org.onechurch.volunteering.application.ServingViews;
import org.onechurch.volunteering.domain.CredentialType;
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

import java.util.List;
import java.util.UUID;

@Tag(name = "Volunteers and serving teams")
public interface VolunteerProfilesApi extends VolunteeringApi {
    @PostMapping("/api/volunteering/volunteers")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "register", description = "Requires volunteering.manage. Versioned commands enforce live permissions. Planning commitments and readiness are evaluated separately; proposed or unready staff do not count toward qualified Children Ministry staffing.")
    Created register(@Valid @RequestBody Register b, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @PutMapping("/api/volunteering/volunteers/{id}/status")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "status", description = "Requires volunteering.manage. Versioned commands enforce live permissions. Planning commitments and readiness are evaluated separately; proposed or unready staff do not count toward qualified Children Ministry staffing.")
    void status(@PathVariable UUID id, @Valid @RequestBody Status b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @PostMapping("/api/volunteering/credential-types")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "type", description = "Requires volunteering.credentials.manage. Versioned commands enforce live permissions. Planning commitments and readiness are evaluated separately; proposed or unready staff do not count toward qualified Children Ministry staffing.")
    Created type(@Valid @RequestBody CredentialTypeDraft b, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @PostMapping("/api/volunteering/volunteers/{id}/credentials")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "credential", description = "Requires volunteering.credentials.manage. Versioned commands enforce live permissions. Planning commitments and readiness are evaluated separately; proposed or unready staff do not count toward qualified Children Ministry staffing.")
    Created credential(@PathVariable UUID id, @Valid @RequestBody CredentialDraft b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @PostMapping("/api/volunteering/volunteers/{id}/credentials/revoke")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "revokeCredential", description = "Requires volunteering.credentials.manage. Versioned commands enforce live permissions. Planning commitments and readiness are evaluated separately; proposed or unready staff do not count toward qualified Children Ministry staffing.")
    void revokeCredential(@PathVariable UUID id, @Valid @RequestBody CredentialRevoke b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @PutMapping("/api/volunteering/volunteers/{id}/availability")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "availability", description = "Requires volunteering.manage. Versioned commands enforce live permissions. Planning commitments and readiness are evaluated separately; proposed or unready staff do not count toward qualified Children Ministry staffing.")
    void availability(@PathVariable UUID id, @Valid @RequestBody Availability b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @GetMapping("/api/volunteering/volunteers")
    @Operation(summary = "volunteers", description = "Requires volunteering.read. Read-only, bounded results; credential evidence is excluded from ordinary views.")
    ServingViews.Volunteers volunteers(@RequestParam(defaultValue = "") String search,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/volunteering/volunteers/{id}")
    @Operation(summary = "volunteer", description = "Requires volunteering.read. Read-only, bounded results; credential evidence is excluded from ordinary views.")
    ServingViews.Profile volunteer(@PathVariable UUID id);
    @GetMapping("/api/volunteering/credential-types")
    @Operation(summary = "credentialTypes", description = "Requires volunteering.read. Read-only, bounded results; credential evidence is excluded from ordinary views.")
    List<CredentialType> credentialTypes();
}
