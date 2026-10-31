package org.onechurch.childrenministry.safeguarding.presentation.api;
import org.onechurch.childrenministry.safeguarding.application.SafeguardingView;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.media.Schema;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.time.Instant;
import java.util.UUID;
@Tag(name = "Children Safeguarding")
@RequestMapping("/api/children")
public interface SafeguardingApi {
    @Schema(name = "ChildrenSafeguardingCreated")
    record Created(UUID id) {}
    @Schema(name = "ChildrenVerifyGuardianRequest")
    record VerifyGuardianRequest(@NotNull UUID childId, @NotNull UUID guardianId, @NotNull Instant validFrom,
        @NotNull Instant validUntil, @Size(max = 2000) String evidenceReference) {}
    @PostMapping("/guardian-authorities")
    @Operation(summary = "Verify Guardian", description = "Requires children.safeguarding. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.CREATED)
    Created verifyGuardian(@Valid @RequestBody VerifyGuardianRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenRecordConsentRequest")
    record RecordConsentRequest(@NotNull UUID childId, @NotNull UUID programmeId, @NotNull UUID policyId,
        @NotNull UUID authorityId, @NotNull Instant validFrom, @NotNull Instant validUntil, @NotNull Instant acceptedAt,
        @Size(max = 2000) String evidenceReference) {}
    @PostMapping("/consents")
    @Operation(summary = "Record Consent", description = "Requires children.safeguarding. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.CREATED)
    Created recordConsent(@Valid @RequestBody RecordConsentRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenRevokeGuardianRequest")
    record RevokeGuardianRequest(@NotNull UUID id, @Min(0) long expectedVersion, @Size(max = 2000) String reason) {}
    @PostMapping("/guardian-authorities/revoke")
    @Operation(summary = "Revoke Guardian", description = "Requires children.safeguarding. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    void revokeGuardian(@Valid @RequestBody RevokeGuardianRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenRevokeConsentRequest")
    record RevokeConsentRequest(@NotNull UUID id, @Min(0) long expectedVersion, @Size(max = 2000) String reason) {}
    @PostMapping("/consents/revoke")
    @Operation(summary = "Revoke Consent", description = "Requires children.safeguarding. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    void revokeConsent(@Valid @RequestBody RevokeConsentRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenRevokePickupAuthorizationRequest")
    record RevokePickupAuthorizationRequest(@NotNull UUID id, @Min(0) long expectedVersion,
        @Size(max = 2000) String reason) {}
    @PostMapping("/pickup-authorizations/revoke")
    @Operation(summary = "Revoke Pickup Authorization", description = "Requires children.safeguarding. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    void revokePickupAuthorization(@Valid @RequestBody RevokePickupAuthorizationRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @Schema(name = "ChildrenAuthorizePickupRequest")
    record AuthorizePickupRequest(@NotNull UUID programmeId, @NotNull UUID childId, @NotNull UUID collectorId,
        @NotNull UUID authorityId, @NotNull Instant validFrom, @NotNull Instant validUntil,
        @Size(max = 2000) String evidenceReference) {}
    @PostMapping("/pickup-authorizations")
    @Operation(summary = "Authorize Pickup", description = "Requires children.safeguarding. Version conflicts require reloading the current resource.")
    @ResponseStatus(HttpStatus.CREATED)
    Created authorizePickup(@Valid @RequestBody AuthorizePickupRequest body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @GetMapping("/safeguarding/{childId}")
    @Operation(summary = "Get Safeguarding", description = "Requires children.safeguarding. Version conflicts require reloading the current resource.")
    SafeguardingView getSafeguarding(@PathVariable("childId") UUID childId);
}
