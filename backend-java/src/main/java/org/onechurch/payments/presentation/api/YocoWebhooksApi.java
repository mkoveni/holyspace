package org.onechurch.payments.presentation.api;
import org.onechurch.payments.application.YocoWebhookManagement;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestParam;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.util.UUID;
@Tag(name = "Payment configuration")
public interface YocoWebhooksApi {
    record Change(@NotNull UUID requestId, @NotBlank String mode, @Min(0) long expectedVersion,
        @NotBlank @Size(max = 500) String reason) {}
    record Close(@Min(0) long expectedVersion, @NotBlank @Size(max = 500) String reason) {}
    @GetMapping("/api/payments/settings/yoco/webhooks")
    @Operation(summary = "List Yoco subscriptions and unresolved operations for an environment", description = "Requires payments.configure. Provider GET only; never returns signing secrets.")
    ResponseEntity<YocoWebhookManagement.Listing> list(@RequestParam String mode,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @PostMapping("/api/payments/settings/yoco/webhooks")
    @Operation(summary = "Register the saved backend callback and encrypt its signing secret", description = "Requires payments.configure. Save disabled first. Reuse requestId after uncertain outcomes; the same request never registers twice. READY operations retain encrypted secrets until explicitly applied against matching settings. Does not activate payments.")
    YocoWebhookManagement.Result register(@Valid @RequestBody Change body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @PostMapping("/api/payments/settings/yoco/webhooks/{id}/removal")
    @Operation(summary = "Remove a reviewed obsolete subscription", description = "Requires payments.configure and a reason. Refuses callbacks referenced by existing payments. Pending removals prevent new Yoco activation/checkouts until resolved.")
    YocoWebhookManagement.Result remove(@PathVariable String id, @Valid @RequestBody Change body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @PostMapping("/api/payments/settings/yoco/webhook-operations/{id}/close")
    @Operation(summary = "Close an uncertain operation after manual provider review", description = "Requires payments.configure and evidence. Does not make a provider mutation. Disables new Yoco checkouts and invalidates the connection check; inspect provider state and test again.")
    void close(@PathVariable UUID id, @Valid @RequestBody Close body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
}
