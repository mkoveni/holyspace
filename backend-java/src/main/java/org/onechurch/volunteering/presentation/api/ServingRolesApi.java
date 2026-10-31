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
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.util.List;
import java.util.UUID;

@Tag(name = "Volunteers and serving teams")
public interface ServingRolesApi {
    @Schema(name = "ServingServingRolesEdit")
    record Edit(@Min(0) long expectedVersion, @NotBlank @Size(max = 100) String name, @NotBlank String childrenRole,
        boolean active, @NotNull @Size(max = 30) List<UUID> requiredCredentials) {}
    @PutMapping("/api/volunteering/teams/{teamId}/roles/{roleId}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Update role requirements or archive a role", description = "Requires volunteering.manage and the current team version. Readiness immediately reflects the new requirements; historical commitments are retained.")
    void edit(@PathVariable UUID teamId, @PathVariable UUID roleId, @Valid @RequestBody Edit body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
}
