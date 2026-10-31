package org.onechurch.payments.presentation.api;
import org.onechurch.payments.application.command.retrypaymentposting.RetryPaymentPostingHandler;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;

import java.util.UUID;
@Tag(name = "Payments")
public interface PaymentPostingApi {
    record Body(@Min(0) long expectedVersion) {}
    @PostMapping("/api/payments/{orderId}/posting-retries")
    @Operation(summary = "Retry verified live Giving posting without collecting another payment", description = "Requires payments.manage. No settlement amounts or provider calls. Safe to retry; returns complete=false when posting still needs attention.")
    RetryPaymentPostingHandler.Result retry(@PathVariable UUID orderId, @Valid @RequestBody Body body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
}
