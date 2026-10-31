package org.onechurch.people.presentation.api;

import org.onechurch.people.application.HouseholdView;
import org.onechurch.people.domain.Household;
import org.onechurch.people.presentation.api.PeopleApi.Created;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.media.Schema;
import io.swagger.v3.oas.annotations.responses.ApiResponse;
import io.swagger.v3.oas.annotations.responses.ApiResponses;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Max;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.util.List;
import java.util.UUID;

@Tag(name = "Households", description = "Family groupings independent of parentage. Read requires people.read; writes require people.manage.")
@RequestMapping("/api/households")
@ApiResponses({@ApiResponse(responseCode = "400", ref = "#/components/responses/BadRequest"),
    @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
    @ApiResponse(responseCode = "403", ref = "#/components/responses/Forbidden"),
    @ApiResponse(responseCode = "404", ref = "#/components/responses/NotFound"),
    @ApiResponse(responseCode = "409", ref = "#/components/responses/Conflict")})
public interface HouseholdApi {
    @Schema(name = "HouseholdCreateRequest")
    record Create(@NotBlank @Size(max = 160) String name) {}
    @Schema(name = "HouseholdRenameRequest")
    record Rename(@Min(0) long expectedVersion, @NotBlank @Size(max = 160) String name) {}
    @Schema(name = "HouseholdAddMemberRequest")
    record AddMember(@Min(0) long expectedVersion, @NotNull UUID personId, boolean primary) {}
    @Schema(name = "HouseholdContactRequest")
    record Contact(@Min(0) long expectedVersion, UUID membershipId) {}
    @Schema(name = "HouseholdRevisionRequest")
    record Revision(@Min(0) long expectedVersion) {}

    @GetMapping
    @Operation(summary = "Search households")
    List<Household> search(@RequestParam(defaultValue = "") @Size(max = 200) String search,
        @RequestParam(defaultValue = "50") @Min(1) @Max(100) int limit,
        @RequestParam(defaultValue = "0") @Min(0) @Max(1000000) int offset);
    @GetMapping("/{id}")
    @Operation(summary = "Get a household and membership history")
    HouseholdView get(@PathVariable UUID id);
    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Create a household")
    Created create(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @Valid @RequestBody Create request);
    @PutMapping("/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Rename a household")
    void update(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @PathVariable UUID id,
        @Valid @RequestBody Rename request);
    @PostMapping("/{id}/members")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Add a household member", description = "Begins now. One primary household per person, but multiple household memberships are allowed. Increments household version.")
    Created member(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @PathVariable UUID id,
        @Valid @RequestBody AddMember request);
    @PostMapping("/{id}/members/{membershipId}/end")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "End a household membership", description = "Ends now and clears the primary contact if necessary. Retains history. Increments household version.")
    void end(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @PathVariable UUID id,
        @PathVariable UUID membershipId, @Valid @RequestBody Revision request);
    @PutMapping("/{id}/primary-contact")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Set or clear the primary contact", description = "Membership must be current and belong to this household. Null clears the contact. No guardianship or pickup rights are conferred.")
    void contact(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @PathVariable UUID id,
        @Valid @RequestBody Contact request);
}
