package org.onechurch.identityaccess.presentation.api;

import org.onechurch.identityaccess.application.AccountView;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.DeleteMapping;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.media.Content;
import io.swagger.v3.oas.annotations.media.Schema;
import io.swagger.v3.oas.annotations.responses.ApiResponse;
import io.swagger.v3.oas.annotations.responses.ApiResponses;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Email;
import jakarta.validation.constraints.Max;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Size;

import java.util.List;
import java.util.Set;
import java.util.UUID;

@Tag(name = "Accounts", description = "Account administration. Every operation requires identity.manage.")
@RequestMapping("/api/users")
public interface AccountApi {

    record Created(UUID id) {}

    record Page(List<AccountView> items) {}

    record Create(@NotBlank @Size(max = 80) String username, @NotBlank @Email @Size(max = 254) String email,
        @NotBlank @Size(max = 160) String displayName,
        @NotBlank @Size(min = 12, max = 72) @Schema(format = "password", accessMode = Schema.AccessMode.WRITE_ONLY) String password) {}

    record Profile(@NotBlank @Email @Size(max = 254) String email, @NotBlank @Size(max = 160) String displayName) {}

    record Password(
        @NotBlank @Size(min = 12, max = 72) @Schema(format = "password", accessMode = Schema.AccessMode.WRITE_ONLY) String password) {}

    @Operation(summary = "Search accounts", description = "Search by username, email or display name. Returns at most 100 accounts.")
    @ApiResponses({@ApiResponse(responseCode = "200", description = "Completed successfully."),
        @ApiResponse(responseCode = "400", ref = "#/components/responses/BadRequest"),
        @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
        @ApiResponse(responseCode = "403", ref = "#/components/responses/Forbidden")})
    @GetMapping
    Page list(@RequestParam(defaultValue = "") @Size(max = 254) String search,
        @RequestParam(defaultValue = "50") @Min(1) @Max(100) int limit);

    @Operation(summary = "Get an account", description = "Returns account details, roles and effective permissions.")
    @ApiResponses({@ApiResponse(responseCode = "200", description = "Completed successfully."),
        @ApiResponse(responseCode = "400", ref = "#/components/responses/BadRequest"),
        @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
        @ApiResponse(responseCode = "403", ref = "#/components/responses/Forbidden"),
        @ApiResponse(responseCode = "404", ref = "#/components/responses/NotFound")})
    @GetMapping("/{id}")
    AccountView get(@PathVariable UUID id);

    @Operation(summary = "Get account permissions", description = "Returns effective permissions, including permissions implied by management grants.")
    @ApiResponses({@ApiResponse(responseCode = "200", description = "Completed successfully."),
        @ApiResponse(responseCode = "400", ref = "#/components/responses/BadRequest"),
        @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
        @ApiResponse(responseCode = "403", ref = "#/components/responses/Forbidden"),
        @ApiResponse(responseCode = "404", ref = "#/components/responses/NotFound")})
    @GetMapping("/{id}/permissions")
    Set<String> permissions(@PathVariable UUID id);

    @Operation(summary = "Create an account", description = "Requires a unique username and email address, display name and password.")
    @ApiResponses({@ApiResponse(responseCode = "201", description = "Completed successfully."),
        @ApiResponse(responseCode = "400", ref = "#/components/responses/BadRequest"),
        @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
        @ApiResponse(responseCode = "403", ref = "#/components/responses/Forbidden"),
        @ApiResponse(responseCode = "409", ref = "#/components/responses/Conflict")})
    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    Created create(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @Valid @RequestBody Create request);

    @Operation(summary = "Update an account", description = "Changes the email address and display name.")
    @ApiResponses({
        @ApiResponse(responseCode = "204", description = "Completed successfully. No response body.", content = @Content),
        @ApiResponse(responseCode = "400", ref = "#/components/responses/BadRequest"),
        @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
        @ApiResponse(responseCode = "403", ref = "#/components/responses/Forbidden"),
        @ApiResponse(responseCode = "404", ref = "#/components/responses/NotFound"),
        @ApiResponse(responseCode = "409", ref = "#/components/responses/Conflict")})
    @PutMapping("/{id}")
    ResponseEntity<Void> update(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @PathVariable UUID id,
        @Valid @RequestBody Profile request);

    @Operation(summary = "Reset an account password", description = "Administrator reset. Revokes all sessions for the account.")
    @ApiResponses({
        @ApiResponse(responseCode = "204", description = "Completed successfully. No response body.", content = @Content),
        @ApiResponse(responseCode = "400", ref = "#/components/responses/BadRequest"),
        @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
        @ApiResponse(responseCode = "403", ref = "#/components/responses/Forbidden"),
        @ApiResponse(responseCode = "404", ref = "#/components/responses/NotFound")})
    @PutMapping("/{id}/password")
    ResponseEntity<Void> password(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @PathVariable UUID id,
        @Valid @RequestBody Password request);

    @Operation(summary = "Disable an account", description = "Revokes sessions. The last active account manager cannot be disabled.")
    @ApiResponses({
        @ApiResponse(responseCode = "204", description = "Completed successfully. No response body.", content = @Content),
        @ApiResponse(responseCode = "400", ref = "#/components/responses/BadRequest"),
        @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
        @ApiResponse(responseCode = "403", ref = "#/components/responses/Forbidden"),
        @ApiResponse(responseCode = "404", ref = "#/components/responses/NotFound"),
        @ApiResponse(responseCode = "409", ref = "#/components/responses/Conflict")})
    @PostMapping("/{id}/disable")
    ResponseEntity<Void> disable(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @PathVariable UUID id);

    @Operation(summary = "Enable an account", description = "Re-enables the account. Previously revoked sessions remain invalid.")
    @ApiResponses({
        @ApiResponse(responseCode = "204", description = "Completed successfully. No response body.", content = @Content),
        @ApiResponse(responseCode = "400", ref = "#/components/responses/BadRequest"),
        @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
        @ApiResponse(responseCode = "403", ref = "#/components/responses/Forbidden"),
        @ApiResponse(responseCode = "404", ref = "#/components/responses/NotFound"),
        @ApiResponse(responseCode = "409", ref = "#/components/responses/Conflict")})
    @PostMapping("/{id}/enable")
    ResponseEntity<Void> enable(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @PathVariable UUID id);

    @Operation(summary = "Assign an account role", description = "Assigns a role to an account. Repeated assignments are idempotent.")
    @ApiResponses({
        @ApiResponse(responseCode = "204", description = "Completed successfully. No response body.", content = @Content),
        @ApiResponse(responseCode = "400", ref = "#/components/responses/BadRequest"),
        @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
        @ApiResponse(responseCode = "403", ref = "#/components/responses/Forbidden"),
        @ApiResponse(responseCode = "404", ref = "#/components/responses/NotFound"),
        @ApiResponse(responseCode = "409", ref = "#/components/responses/Conflict")})
    @PostMapping("/{id}/roles/{roleId}")
    ResponseEntity<Void> assign(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @PathVariable UUID id,
        @PathVariable UUID roleId);

    @Operation(summary = "Remove an account role", description = "Removes a role. At least one active account manager must remain.")
    @ApiResponses({
        @ApiResponse(responseCode = "204", description = "Completed successfully. No response body.", content = @Content),
        @ApiResponse(responseCode = "400", ref = "#/components/responses/BadRequest"),
        @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
        @ApiResponse(responseCode = "403", ref = "#/components/responses/Forbidden"),
        @ApiResponse(responseCode = "404", ref = "#/components/responses/NotFound"),
        @ApiResponse(responseCode = "409", ref = "#/components/responses/Conflict")})
    @DeleteMapping("/{id}/roles/{roleId}")
    ResponseEntity<Void> remove(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor, @PathVariable UUID id,
        @PathVariable UUID roleId);
}
