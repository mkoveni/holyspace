package org.onechurch.payments.presentation.api;
import org.onechurch.payments.application.contract.DonorPaymentReader;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.payments.application.query.getmygiving.GetMyGivingHandler;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.DecimalMax;
import jakarta.validation.constraints.DecimalMin;
import jakarta.validation.constraints.Digits;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;

import java.math.BigDecimal;
import java.util.UUID;
@Tag(name = "My giving", description = "Signed-in donor self-service. Every request checks a live session and explicitly verified People link. No donor ID is accepted from the caller. Browser returns do not verify payment.")
public interface DonorGivingApi {
    record StartBody(@NotNull UUID requestId, @NotNull UUID fundId,
        @NotNull @DecimalMin("500") @DecimalMax("100000000") @Digits(integer = 9, fraction = 0) BigDecimal amountMinor,
        @NotBlank String provider, boolean recurring) {}
    @GetMapping("/api/my-giving")
    @Operation(summary = "Read own giving, online funds and payment history", description = "Unlinked accounts receive an empty history and a null donor. Pages are bounded; no financial administration permission is granted.")
    ResponseEntity<GetMyGivingHandler.Result> overview(@RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int paymentOffset, @RequestParam(defaultValue = "0") int giftOffset);
    @GetMapping("/api/my-giving/payments/{orderId}")
    @Operation(summary = "Read own payment progress, refunds and confirmation status", description = "Excludes bank settlement, private staff notes, contact addresses and provider tokens.")
    ResponseEntity<DonorPaymentReader.Detail> detail(@PathVariable UUID orderId);
    @PostMapping("/api/my-giving/payments")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Request an online gift for the verified current donor", description = "Reuse requestId after uncertainty. Only explicitly offered active funds are accepted. Payfast supports monthly giving. Test mode never posts a real contribution.")
    PaymentsApi.Created start(@Valid @RequestBody StartBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @PostMapping("/api/my-giving/payments/{orderId}/checkout")
    @Operation(summary = "Open or resume own hosted checkout")
    ResponseEntity<PaymentGateway.Checkout> checkout(@PathVariable UUID orderId,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @PostMapping("/api/my-giving/payments/{orderId}/subscription")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Check or cancel own monthly giving", description = "Requires the current order version. Cancellation stays requested until the provider confirms; in-flight charges are retained.")
    void subscription(@PathVariable UUID orderId, @Valid @RequestBody PaymentsApi.SubscriptionBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
}
