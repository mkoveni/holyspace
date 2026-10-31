package org.onechurch.payments.presentation.api;
import org.onechurch.payments.application.command.preparemobilecheckout.PrepareMobileCheckoutHandler;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.security.SecurityRequirements;
import io.swagger.v3.oas.annotations.tags.Tag;

import java.util.UUID;
@Tag(name = "Mobile giving", description = "Short-lived one-use browser handoff. Account session tokens never appear in checkout URLs. Provider verification remains authoritative.")
public interface MobileCheckoutApi {
    @PostMapping("/api/my-giving/payments/{orderId}/mobile-checkout")
    @Operation(summary = "Prepare my secure mobile checkout", description = "Requires the live donor session. The five-minute URL can be opened in the system browser; completing its POST consumes it once. Reopening an expired/used handoff requires preparing a fresh URL for the same payment.")
    ResponseEntity<PrepareMobileCheckoutHandler.Handoff> prepare(@PathVariable UUID orderId,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @GetMapping(value = "/api/mobile-checkouts/{token}", produces = "text/html")
    @Operation(hidden = true)
    @SecurityRequirements
    ResponseEntity<String> landing(@PathVariable String token);
    @PostMapping(value = "/api/mobile-checkouts/{token}", produces = "text/html")
    @Operation(hidden = true)
    @SecurityRequirements
    ResponseEntity<String> complete(@PathVariable String token);
}
