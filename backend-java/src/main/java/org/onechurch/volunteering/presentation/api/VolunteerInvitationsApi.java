package org.onechurch.volunteering.presentation.api;

import org.onechurch.volunteering.application.ServingViews;
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

import java.util.List;
import java.util.UUID;

@Tag(name = "Volunteers and serving teams")
public interface VolunteerInvitationsApi extends VolunteeringApi {
    @PostMapping("/api/volunteering/assignments/{id}/invitations")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "invite", description = "Requires volunteering.manage. Versioned commands enforce live permissions. Planning commitments and readiness are evaluated separately; proposed or unready staff do not count toward qualified Children Ministry staffing.")
    Created invite(@PathVariable UUID id, @Valid @RequestBody Invite b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @PostMapping("/api/volunteering/invitations/{id}/respond")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "respond", description = "Requires volunteering.serve. Versioned commands enforce live permissions. Planning commitments and readiness are evaluated separately; proposed or unready staff do not count toward qualified Children Ministry staffing.")
    void respond(@PathVariable UUID id, @Valid @RequestBody Respond b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @PostMapping("/api/volunteering/invitations/expire")
    @Operation(summary = "expire", description = "Requires volunteering.manage. Versioned commands enforce live permissions. Planning commitments and readiness are evaluated separately; proposed or unready staff do not count toward qualified Children Ministry staffing.")
    int expire(@Valid @RequestBody Expire b, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @GetMapping("/api/volunteering/me/invitations")
    @Operation(summary = "own", description = "Requires volunteering.serve. Read-only, bounded results; credential evidence is excluded from ordinary views.")
    List<ServingViews.InvitationView> own(@RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
}
