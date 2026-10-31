package org.onechurch.payments.presentation.api;
import org.springframework.http.HttpStatus;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestHeader;
import org.springframework.web.bind.annotation.ResponseStatus;

import io.swagger.v3.oas.annotations.Operation;
import io.swagger.v3.oas.annotations.Parameter;
import io.swagger.v3.oas.annotations.security.SecurityRequirements;
import io.swagger.v3.oas.annotations.tags.Tag;
import jakarta.servlet.http.HttpServletRequest;

import java.util.Map;
@Tag(name = "Payment notifications")
public interface PaymentWebhooksApi {
    @PostMapping(value = "/api/payments/webhooks/{provider}", consumes = {"application/json",
        "application/x-www-form-urlencoded"})
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @SecurityRequirements
    @Operation(summary = "Receive an authenticated provider notification", description = "Public callback only. Yoco requires raw-body HMAC and a fresh signing timestamp. Payfast requires trusted source IP, merchant/signature checks and server validation. Invalid callbacks never post gifts. Unknown orders are ignored; transient verification/checkout failures return 503 for retry.")
    void receive(@PathVariable String provider, @RequestBody String body, @RequestHeader Map<String, String> headers,
        @Parameter(hidden = true) HttpServletRequest request);
}
