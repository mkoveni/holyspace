package org.onechurch.volunteering.presentation.api;

import org.onechurch.volunteering.application.ServingViews;
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

import java.util.UUID;

@Tag(name = "Volunteers and serving teams")
public interface ServingTeamsApi extends VolunteeringApi {
    @PostMapping("/api/volunteering/teams")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "createTeam", description = "Requires volunteering.manage. Versioned commands enforce live permissions. Planning commitments and readiness are evaluated separately; proposed or unready staff do not count toward qualified Children Ministry staffing.")
    Created createTeam(@Valid @RequestBody TeamDraft b, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @PutMapping("/api/volunteering/teams/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "updateTeam", description = "Requires volunteering.manage. Versioned commands enforce live permissions. Planning commitments and readiness are evaluated separately; proposed or unready staff do not count toward qualified Children Ministry staffing.")
    void updateTeam(@PathVariable UUID id, @Valid @RequestBody TeamEdit b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @PostMapping("/api/volunteering/teams/{id}/roles")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "role", description = "Requires volunteering.manage. Versioned commands enforce live permissions. Planning commitments and readiness are evaluated separately; proposed or unready staff do not count toward qualified Children Ministry staffing.")
    Created role(@PathVariable UUID id, @Valid @RequestBody Role b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @PostMapping("/api/volunteering/teams/{id}/memberships")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "join", description = "Requires volunteering.manage. Versioned commands enforce live permissions. Planning commitments and readiness are evaluated separately; proposed or unready staff do not count toward qualified Children Ministry staffing.")
    Created join(@PathVariable UUID id, @Valid @RequestBody Membership b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @PostMapping("/api/volunteering/teams/{id}/approvals")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "approve", description = "Requires volunteering.manage. Versioned commands enforce live permissions. Planning commitments and readiness are evaluated separately; proposed or unready staff do not count toward qualified Children Ministry staffing.")
    Created approve(@PathVariable UUID id, @Valid @RequestBody Approval b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @PostMapping("/api/volunteering/teams/{id}/revoke")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "revoke", description = "Requires volunteering.manage. Versioned commands enforce live permissions. Planning commitments and readiness are evaluated separately; proposed or unready staff do not count toward qualified Children Ministry staffing.")
    void revoke(@PathVariable UUID id, @Valid @RequestBody Revoke b,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal p);
    @GetMapping("/api/volunteering/teams")
    @Operation(summary = "teams", description = "Requires volunteering.read. Read-only, bounded results; credential evidence is excluded from ordinary views.")
    ServingViews.Teams teams(@RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/volunteering/teams/{id}")
    @Operation(summary = "team", description = "Requires volunteering.read. Read-only, bounded results; credential evidence is excluded from ordinary views.")
    ServingViews.TeamDetails team(@PathVariable UUID id);
}
