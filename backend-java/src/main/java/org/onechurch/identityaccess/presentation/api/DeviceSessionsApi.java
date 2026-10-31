package org.onechurch.identityaccess.presentation.api;
import org.onechurch.identityaccess.application.query.listmysessions.ListMySessionsHandler;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.DeleteMapping;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;

import java.util.UUID;
@Tag(name = "My devices", description = "Own active sessions. Device names are user-supplied labels, not proof of device identity. No tokens or token hashes are returned.")
public interface DeviceSessionsApi {
    @GetMapping("/api/me/sessions")
    @Operation(summary = "List my active device sessions")
    ResponseEntity<ListMySessionsHandler.Page> list(@RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @DeleteMapping("/api/me/sessions/{sessionId}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Revoke one of my sessions", description = "Allows revoking the current session or another own session. Other accounts' sessions return 404.")
    void revoke(@PathVariable UUID sessionId, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
}
