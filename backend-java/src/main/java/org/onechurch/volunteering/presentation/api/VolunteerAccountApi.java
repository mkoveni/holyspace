package org.onechurch.volunteering.presentation.api;

import org.springframework.http.HttpStatus;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.media.Schema;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;

import java.util.UUID;

@Tag(name = "Volunteers and serving teams")
public interface VolunteerAccountApi {
    @Schema(name = "ServingVolunteerAccountLink")
    record Link(@Min(0) long expectedVersion, UUID accountId) {}
    @PutMapping("/api/volunteering/volunteers/{id}/account")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Link or unlink a volunteer login", description = "Requires both volunteering.manage and identity.manage. Accounts are unique to one volunteer. Does not grant serving permission or create an account.")
    void link(@PathVariable UUID id, @Valid @RequestBody Link body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
}
