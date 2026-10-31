package org.onechurch.payments.presentation.api;
import org.onechurch.payments.application.query.getpaymentreceipt.GetPaymentReceiptHandler;
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
@Tag(name = "Payments")
public interface PaymentReceiptsApi {
    record Approve(@NotNull UUID requestId, @NotNull UUID chargeId, @NotNull UUID contactId,
        @NotBlank @Size(max = 64) String reviewHash, @NotBlank @Size(max = 500) String evidence) {
        @Override
        public String toString() {
            return "ApprovePaymentReceipt[redacted]";
        }
    }
    record Retry(@Min(0) long expectedVersion, @NotBlank @Size(max = 500) String reason) {}
    @GetMapping("/api/payments/{orderId}/receipts")
    @Operation(summary = "Preview a live donor confirmation and inspect delivery status", description = "Requires payments.manage and payments.receipts.send. Read only. Requires a posted, unrefunded live contribution. Optional contactId renders the published template for one of this donor's current addresses.")
    ResponseEntity<GetPaymentReceiptHandler.Result> get(@PathVariable UUID orderId, @RequestParam UUID chargeId,
        @RequestParam(required = false) UUID contactId);
    @org.springframework.web.bind.annotation.ResponseStatus(org.springframework.http.HttpStatus.CREATED)
    @PostMapping("/api/payments/{orderId}/receipts")
    @Operation(summary = "Approve a reviewed donor confirmation for Communications delivery", description = "Requires payments.manage and payments.receipts.send. Rechecks payment, contact, live permissions and published template under lock. Requires recipient verification evidence. One immutable confirmation per charge; identical retries return the existing delivery. Capture/SMTP acceptance never implies confirmed delivery.")
    PaymentsApi.Created approve(@PathVariable UUID orderId, @Valid @RequestBody Approve body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @PostMapping("/api/payments/{orderId}/receipts/{receiptId}/retries")
    @Operation(summary = "Retry a definitely failed or blocked donor confirmation", description = "Requires payments.manage and payments.receipts.send. Original rendered content remains frozen. Uncertain sends cannot be retried automatically.")
    void retry(@PathVariable UUID orderId, @PathVariable UUID receiptId, @Valid @RequestBody Retry body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
}
