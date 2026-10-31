package org.onechurch.payments.presentation.api;
import org.onechurch.payments.application.query.getpaymentsettings.GetPaymentSettingsHandler;
import org.onechurch.payments.domain.ConfigurationCheck;
import org.onechurch.payments.domain.ProviderCredentials;
import org.springframework.http.HttpStatus;
import org.springframework.http.ResponseEntity;
import org.springframework.security.core.annotation.AuthenticationPrincipal;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.media.Schema;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.validation.Valid;
import jakarta.validation.constraints.Min;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;
@Tag(name = "Payment configuration", description = "Requires payments.configure independently of payment management. Secrets are replace-only, encrypted at rest and never returned. Existing orders remain pinned to their original revision.")
public interface PaymentSettingsApi {
    record Credentials(@Size(max = 1000) @Schema(accessMode = Schema.AccessMode.WRITE_ONLY) String merchantId,
        @Size(max = 1000) @Schema(accessMode = Schema.AccessMode.WRITE_ONLY) String merchantKey,
        @Size(max = 1000) @Schema(accessMode = Schema.AccessMode.WRITE_ONLY) String passphrase,
        @Size(max = 1000) @Schema(accessMode = Schema.AccessMode.WRITE_ONLY) String yocoKey,
        @Size(max = 1000) @Schema(accessMode = Schema.AccessMode.WRITE_ONLY) String webhookSecret) {
        public ProviderCredentials credentials() {
            return new ProviderCredentials(merchantId, merchantKey, passphrase, yocoKey, webhookSecret);
        }
        @Override
        public String toString() {
            return "Credentials[redacted]";
        }
    }
    record SaveBody(@Min(0) long expectedVersion, @NotBlank String mode, boolean enabled, boolean monthly,
        @Schema(description = "Public frontend HTTPS origin used for browser returns") @Size(max = 1000) String publicUrl,
        @Size(max = 2000) String ipRanges, @NotNull @Valid Credentials credentials,
        @NotBlank @Size(max = 1000) String reason,
        @Schema(description = "Public backend HTTPS origin used for provider webhooks; omitted values preserve existing settings") @Size(max = 1000) String backendUrl) {
        @Override
        public String toString() {
            return "SaveBody[credentials=redacted]";
        }
    }
    record CheckBody(@Min(0) long expectedVersion, @NotBlank String mode) {}
    @GetMapping("/api/payments/settings")
    @Operation(summary = "Read redacted provider settings and the latest 100 configuration changes", description = "Read only. Returns presence indicators rather than credential values. Encryption master key is deployment-owned.")
    ResponseEntity<GetPaymentSettingsHandler.Result> settings();
    @PutMapping("/api/payments/settings/{provider}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Operation(summary = "Save provider controls and replace supplied credentials", description = "Blank credentials keep the saved values for that environment. Test/live credentials are independent. Changed connection details must be saved disabled, checked, then activated. Existing payment/subscription configurations remain immutable. Requires current version and a reason.")
    void save(@PathVariable("provider") String provider, @Valid @RequestBody SaveBody body,
        @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
    @PostMapping("/api/payments/settings/{provider}/connection-check")
    @Operation(summary = "Explicitly test saved provider connectivity without charging", description = "Checks run outside database transactions. Payfast uses authenticated ping; Yoco checks API access and callback registration. This does not create a checkout or verify real settlement. Successful checks are required within 24 hours before activation. Failed checks return a safe result and never expose provider responses or credentials.")
    ResponseEntity<ConfigurationCheck> check(@PathVariable("provider") String provider,
        @Valid @RequestBody CheckBody body, @AuthenticationPrincipal OAuth2AuthenticatedPrincipal actor);
}
