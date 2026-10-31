package org.onechurch.payments.presentation.api;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.payments.application.contract.PaymentReader;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.media.Schema;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.DecimalMax;
import jakarta.validation.constraints.DecimalMin;
import jakarta.validation.constraints.Digits;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.math.BigDecimal;
import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
@Tag(name = "Payments", description = "Staff-assisted hosted ZAR giving. Pending checkouts and sandbox charges never post real gifts. Browser returns are not payment evidence.")
public interface PaymentsApi {
    record Created(UUID id) {}
    record StartBody(@NotNull UUID requestId, @NotNull UUID donorId, @NotNull UUID fundId,
        @NotNull @DecimalMin("500") @DecimalMax("100000000") @Digits(integer = 9, fraction = 0) @Schema(implementation = Long.class, types = "integer", format = "int64") BigDecimal amountMinor,
        @NotBlank String provider, Boolean recurring) {}
    record RefundBody(@NotNull UUID chargeId, @NotBlank @Size(max = 150) String providerReference,
        @NotNull @DecimalMin("1") @DecimalMax("100000000") @Digits(integer = 9, fraction = 0) @Schema(implementation = Long.class, types = "integer", format = "int64") BigDecimal amountMinor,
        @NotNull LocalDate refundedOn, @NotBlank @Size(max = 1000) String reason) {}
    record ReviewBody(@NotNull UUID requestId, @NotNull UUID chargeId, @Min(0) long expectedVersion,
        @NotBlank @Size(max = 200) String settlementReference,
        @NotNull @DecimalMin("0") @DecimalMax("100000000") @Digits(integer = 9, fraction = 0) @Schema(implementation = Long.class, types = "integer", format = "int64") BigDecimal feeMinor,
        @NotNull @DecimalMin("0") @DecimalMax("100000000") @Digits(integer = 9, fraction = 0) @Schema(implementation = Long.class, types = "integer", format = "int64") BigDecimal bankMinor,
        @NotBlank @Size(max = 1000) String note) {}
    record SubscriptionBody(@Min(0) long expectedVersion, boolean cancel) {}
    @GetMapping("/api/payments/providers")
    @Operation(summary = "Read configured hosted providers")
    List<PaymentGateway.Provider> providers();
    @GetMapping("/api/payments")
    @Operation(summary = "Browse confidential payment orders", description = "Requires payments.manage. Read only, bounded pages; reviewOnly filters operational exceptions.")
    PaymentReader.Page list(@RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset,
        @RequestParam(defaultValue = "false") boolean reviewOnly);
    @GetMapping("/api/payments/{orderId}")
    @Operation(summary = "Read verified charges, refunds and settlement evidence")
    PaymentReader.Detail detail(@PathVariable("orderId") UUID orderId);
    @PostMapping("/api/payments")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Create a durable giving checkout request", description = "Requires payments.manage. Reuse this UUID for an identical retry. Only active People donors/funds; ZAR 5–1,000,000. Monthly giving uses Payfast. No gift is posted here.")
    Created start(@Valid @RequestBody StartBody body, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @PostMapping("/api/payments/{orderId}/checkout")
    @Operation(summary = "Create or resume this same hosted checkout", description = "Requires payments.manage. Provider calls run outside database transactions. Ambiguous outcomes retain the original order and idempotency key. Payment details are entered only at the provider.")
    PaymentGateway.Checkout checkout(@PathVariable("orderId") UUID orderId,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @PostMapping("/api/payments/{orderId}/refunds")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Record a provider-completed refund", description = "Requires payments.manage and payments.refund. This does not issue a financial refund. Supply the exact provider refund ID and matched charge; retries cannot duplicate money. Supports partial and full refunds.")
    void refund(@PathVariable("orderId") UUID orderId, @Valid @RequestBody RefundBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @PostMapping("/api/payments/{orderId}/reviews")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Retry verified ledger posting and record settlement evidence", description = "Requires payments.manage and payments.reconcile. Never invents a charge. Preserves versioned bank/fee/refund snapshots. Later refunds make settlement review stale. Fees never reduce the donor's gross gift.")
    Created review(@PathVariable("orderId") UUID orderId, @Valid @RequestBody ReviewBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @PostMapping("/api/payments/{orderId}/subscription")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Check or cancel monthly giving", description = "Requires payments.manage and current order version. Cancellation stays requested until provider confirmation. In-flight charges remain verifiable after cancellation.")
    void subscription(@PathVariable("orderId") UUID orderId, @Valid @RequestBody SubscriptionBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
}
