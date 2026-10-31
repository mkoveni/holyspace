package org.onechurch.people.presentation.api;

import org.onechurch.people.domain.RelationshipType;
import org.onechurch.people.presentation.api.PeopleApi.Created;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.media.Schema;
import io.swagger.v3.oas.annotations.responses.ApiResponse;
import io.swagger.v3.oas.annotations.responses.ApiResponses;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotNull;

import java.util.UUID;

@Tag(name = "Relationships", description = "Directed parent/guardian facts and canonical symmetric relationships. Requires people.manage.")
@RequestMapping("/api/relationships")
@ApiResponses({@ApiResponse(responseCode = "400", ref = "#/components/responses/BadRequest"),
    @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
    @ApiResponse(responseCode = "403", ref = "#/components/responses/Forbidden"),
    @ApiResponse(responseCode = "404", ref = "#/components/responses/NotFound"),
    @ApiResponse(responseCode = "409", ref = "#/components/responses/Conflict")})
public interface RelationshipApi {
    @Schema(name = "RelationshipCreateRequest")
    record Create(@NotNull UUID sourceId, @NotNull UUID targetId, @NotNull RelationshipType type) {}
    @Schema(name = "RelationshipRevisionRequest")
    record Revision(@Min(0) long expectedVersion) {}
    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Create a relationship", description = "Begins now. Rejects self-links, overlapping duplicate facts and parentage cycles. Symmetric links are stored once.")
    Created create(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @Valid @RequestBody Create request);
    @PostMapping("/{id}/end")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "End a relationship", description = "Ends now, preserving the fact's history. Stale revisions return 409.")
    void end(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @PathVariable UUID id,
        @Valid @RequestBody Revision request);
}
