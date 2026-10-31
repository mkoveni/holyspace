package org.onechurch.childrenministry.messaging.presentation.api;
import org.onechurch.childrenministry.messaging.application.contract.DeliveryReader;
import org.onechurch.childrenministry.messaging.application.query.getemaildelivery.GetEmailDeliveryHandler;
import org.onechurch.childrenministry.messaging.application.query.getemailreview.GetEmailReviewHandler;
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
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;

import java.time.Instant;
import java.util.UUID;
@Tag(name = "Children email delivery")
public interface EmailDeliveryApi {
    record Created(UUID id) {}
    record Verify(@NotNull UUID contactId, @NotBlank @Size(max = 500) String evidence, @NotNull Instant validUntil) {}
    record Queue(@Min(0) long expectedVersion, @NotNull UUID verificationId, @NotBlank String reviewHash,
        Instant scheduledAt) {}
    record Change(@Min(0) long expectedVersion, @NotBlank @Size(max = 500) String reason) {}
    record Revoke(@NotBlank @Size(max = 500) String reason) {}
    record Receipt(@NotNull UUID deliveryId, @NotBlank String messageId, @NotBlank @Size(max = 200) String eventKey,
        @NotBlank String status, @NotBlank @Size(max = 500) String reference) {}
    @GetMapping("/api/children/guardian-communications/{id}/email-review")
    @Operation(summary = "Review email recipient and wording", description = "Requires children.safeguarding. Reads active People email contacts through a published contract. Includes coordinator verification and a fingerprint binding approval to unchanged content and recipient. No send occurs.")
    GetEmailReviewHandler.View review(@PathVariable UUID id);
    @PostMapping("/api/children/guardian-communications/{id}/verify-email")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Record guardian email verification", description = "Requires children.safeguarding. Record evidence that the address was verified with this guardian; this is coordinator verification, not an automated ownership challenge. Expiry at most one year. Contact changes, revocation and expiry invalidate approval.")
    Created verify(@PathVariable UUID id, @Valid @RequestBody Verify body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @PostMapping("/api/children/verified-emails/{id}/revoke")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Revoke email verification", description = "Requires children.safeguarding. Rechecked before scheduled dispatch.")
    void revoke(@PathVariable UUID id, @Valid @RequestBody Revoke body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @PostMapping("/api/children/guardian-communications/{id}/queue-email")
    @ResponseStatus(HttpStatus.CREATED)
    @Operation(summary = "Approve immediate or scheduled email", description = "Requires children.communications.send and children.safeguarding. Explicit approval covers exact message/recipient fingerprint, version and a send time within 90 days. One delivery per communication prevents duplicate sends. Live sender permission, guardian, contact and ministry eligibility are checked again at dispatch. Saved approval delegates only this message; it does not grant a scheduler administrator access.")
    Created queue(@PathVariable UUID id, @Valid @RequestBody Queue body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @GetMapping("/api/children/email-deliveries")
    @Operation(summary = "List delivery status", description = "Requires children.safeguarding. Metadata only. SENT means SMTP acceptance, never confirmed delivery. Capture-mode sends are local test messages. UNKNOWN is never retried automatically.")
    DeliveryReader.Page list(@RequestParam(defaultValue = "ALL") String status,
        @RequestParam(defaultValue = "25") int limit, @RequestParam(defaultValue = "0") int offset);
    @GetMapping("/api/children/email-deliveries/{id}")
    @Operation(summary = "Review delivery and paged history", description = "Requires children.safeguarding. Contains recipient and approved wording; no incident/care contents. History pages are bounded.")
    GetEmailDeliveryHandler.View get(@PathVariable UUID id, @RequestParam(defaultValue = "25") int limit,
        @RequestParam(defaultValue = "0") int offset);
    @PostMapping("/api/children/email-deliveries/{id}/cancel")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Cancel a waiting delivery", description = "Requires send and safeguarding permissions. Versioned; cannot cancel in-progress, accepted, or uncertain sends.")
    void cancel(@PathVariable UUID id, @Valid @RequestBody Change body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @PostMapping("/api/children/email-deliveries/{id}/retry")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Approve a retry after definite failure", description = "Requires send and safeguarding permissions. Rechecks eligibility, max three attempts. UNKNOWN, SENT and DELIVERED cannot be retried. Stale in-progress sends become UNKNOWN after five minutes.")
    void retry(@PathVariable UUID id, @Valid @RequestBody Change body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
    @PostMapping("/api/children/email-deliveries/receipts")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Accept authenticated provider outcome", description = "Requires dedicated children.communications.receipts permission and a live bearer session for a trusted provider relay. This is not an anonymous webhook. Correlates Message-ID and delivery ID, idempotent eventKey; only SENT or reconciled UNKNOWN outcomes may become DELIVERED/FAILED. Capture-mode receipts cannot claim real delivery. SMTP alone never produces delivery confirmation; configure a provider relay to call this API.")
    void receipt(@Valid @RequestBody Receipt body, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal principal);
}
