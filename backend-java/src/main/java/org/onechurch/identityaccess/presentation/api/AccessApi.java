package org.onechurch.identityaccess.presentation.api;

import org.onechurch.identityaccess.application.contract.AccessReader.Permission;
import org.onechurch.identityaccess.application.contract.AccessReader.Role;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.DeleteMapping;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.media.Content;
import io.swagger.v3.oas.annotations.responses.ApiResponse;
import io.swagger.v3.oas.annotations.responses.ApiResponses;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Size;

import java.util.List;
import java.util.UUID;

@Tag(name = "Access management", description = "Role and permission administration. Every operation requires identity.manage.")
public interface AccessApi {

    record RoleRequest(@NotBlank @Size(max = 80) String name, @Size(max = 255) String description) {}

    record PermissionRequest(@NotBlank @Size(max = 100) String code, @Size(max = 255) String description) {}

    record Created(UUID id) {}

    @Operation(summary = "List roles", description = "Returns the role catalogue.")
    @ApiResponses({@ApiResponse(responseCode = "200", description = "Completed successfully."),
        @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
        @ApiResponse(responseCode = "403", ref = "#/components/responses/Forbidden")})
    @GetMapping("/api/roles")
    List<Role> roles();

    @Operation(summary = "List permissions", description = "Returns the permission catalogue.")
    @ApiResponses({@ApiResponse(responseCode = "200", description = "Completed successfully."),
        @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
        @ApiResponse(responseCode = "403", ref = "#/components/responses/Forbidden")})
    @GetMapping("/api/permissions")
    List<Permission> permissions();

    @Operation(summary = "Create a role", description = "Creates a role using a unique name.")
    @ApiResponses({@ApiResponse(responseCode = "201", description = "Completed successfully."),
        @ApiResponse(responseCode = "400", ref = "#/components/responses/BadRequest"),
        @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
        @ApiResponse(responseCode = "403", ref = "#/components/responses/Forbidden"),
        @ApiResponse(responseCode = "409", ref = "#/components/responses/Conflict")})
    @PostMapping("/api/roles")
    @ResponseStatus(HttpStatus.CREATED)
    Created createRole(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor,
        @Valid @RequestBody RoleRequest request);

    @Operation(summary = "Create a permission", description = "Creates a permission using a context.action code, such as identity.read.")
    @ApiResponses({@ApiResponse(responseCode = "201", description = "Completed successfully."),
        @ApiResponse(responseCode = "400", ref = "#/components/responses/BadRequest"),
        @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
        @ApiResponse(responseCode = "403", ref = "#/components/responses/Forbidden"),
        @ApiResponse(responseCode = "409", ref = "#/components/responses/Conflict")})
    @PostMapping("/api/permissions")
    @ResponseStatus(HttpStatus.CREATED)
    Created createPermission(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor,
        @Valid @RequestBody PermissionRequest request);

    @Operation(summary = "Assign a permission to a role", description = "Grants a permission to every account holding the role.")
    @ApiResponses({
        @ApiResponse(responseCode = "204", description = "Completed successfully. No response body.", content = @Content),
        @ApiResponse(responseCode = "400", ref = "#/components/responses/BadRequest"),
        @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
        @ApiResponse(responseCode = "403", ref = "#/components/responses/Forbidden"),
        @ApiResponse(responseCode = "404", ref = "#/components/responses/NotFound"),
        @ApiResponse(responseCode = "409", ref = "#/components/responses/Conflict")})
    @PostMapping("/api/roles/{roleId}/permissions/{permissionId}")
    ResponseEntity<Void> assign(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @PathVariable UUID roleId,
        @PathVariable UUID permissionId);

    @Operation(summary = "Remove a permission from a role", description = "Removes a permission. At least one active account manager must remain.")
    @ApiResponses({
        @ApiResponse(responseCode = "204", description = "Completed successfully. No response body.", content = @Content),
        @ApiResponse(responseCode = "400", ref = "#/components/responses/BadRequest"),
        @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
        @ApiResponse(responseCode = "403", ref = "#/components/responses/Forbidden"),
        @ApiResponse(responseCode = "404", ref = "#/components/responses/NotFound"),
        @ApiResponse(responseCode = "409", ref = "#/components/responses/Conflict")})
    @DeleteMapping("/api/roles/{roleId}/permissions/{permissionId}")
    ResponseEntity<Void> remove(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @PathVariable UUID roleId,
        @PathVariable UUID permissionId);
}
