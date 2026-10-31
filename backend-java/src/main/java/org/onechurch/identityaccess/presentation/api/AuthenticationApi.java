package org.onechurch.identityaccess.presentation.api;

import org.onechurch.identityaccess.application.AccountView;
import org.onechurch.identityaccess.application.command.login.LoginHandler;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.media.Content;
import io.swagger.v3.oas.annotations.media.Schema;
import io.swagger.v3.oas.annotations.responses.ApiResponse;
import io.swagger.v3.oas.annotations.responses.ApiResponses;
import io.swagger.v3.oas.annotations.security.SecurityRequirements;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Size;

@Tag(name = "Authentication", description = "Sign in, inspect the current account, change passwords and revoke sessions.")
public interface AuthenticationApi {

    record Login(@NotBlank @Size(max = 254) String username,
        @NotBlank @Size(max = 72) @Schema(format = "password", accessMode = Schema.AccessMode.WRITE_ONLY) String password,
        @Size(max = 80) String deviceName) {}

    record PasswordChange(
        @NotBlank @Size(max = 72) @Schema(format = "password", accessMode = Schema.AccessMode.WRITE_ONLY) String currentPassword,
        @NotBlank @Size(min = 12, max = 72) @Schema(format = "password", accessMode = Schema.AccessMode.WRITE_ONLY) String password) {}

    @Operation(summary = "Sign in", description = "Accepts a username or email address. Returns an opaque bearer token. Failed attempts are rate limited.")
    @SecurityRequirements
    @ApiResponses({@ApiResponse(responseCode = "200", description = "Completed successfully."),
        @ApiResponse(responseCode = "400", ref = "#/components/responses/BadRequest"),
        @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
        @ApiResponse(responseCode = "429", ref = "#/components/responses/TooManyRequests")})
    @PostMapping("/api/login_check")
    LoginHandler.Result login(@Valid @RequestBody Login request);

    @Operation(summary = "Get my account", description = "Returns the authenticated account with its current roles and effective permissions.")
    @ApiResponses({@ApiResponse(responseCode = "200", description = "Completed successfully."),
        @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized")})
    @GetMapping("/api/me")
    AccountView me(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);

    @Operation(summary = "Sign out", description = "Revokes the current session immediately.")
    @ApiResponses({
        @ApiResponse(responseCode = "204", description = "Completed successfully. No response body.", content = @Content),
        @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized")})
    @PostMapping("/api/logout")
    ResponseEntity<Void> logout(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);

    @Operation(summary = "Change my password", description = "Requires the current password. Revokes all sessions, including the current session.")
    @ApiResponses({
        @ApiResponse(responseCode = "204", description = "Completed successfully. No response body.", content = @Content),
        @ApiResponse(responseCode = "400", ref = "#/components/responses/BadRequest"),
        @ApiResponse(responseCode = "401", ref = "#/components/responses/Unauthorized"),
        @ApiResponse(responseCode = "403", ref = "#/components/responses/Forbidden"),
        @ApiResponse(responseCode = "404", ref = "#/components/responses/NotFound")})
    @PutMapping("/api/me/password")
    ResponseEntity<Void> changePassword(@AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal,
        @Valid @RequestBody PasswordChange request);
}
