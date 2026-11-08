package org.onechurch.payments.presentation;
import org.onechurch.payments.application.command.completemobilecheckout.CompleteMobileCheckoutCommand;
import org.onechurch.payments.application.command.completemobilecheckout.CompleteMobileCheckoutHandler;
import org.onechurch.payments.application.command.preparemobilecheckout.PrepareMobileCheckoutCommand;
import org.onechurch.payments.application.command.preparemobilecheckout.PrepareMobileCheckoutHandler;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.presentation.api.MobileCheckoutApi;
import org.springframework.http.CacheControl;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.util.HtmlUtils;

import java.net.URI;
import java.util.List;
import java.util.UUID;
import java.util.stream.Collectors;
@RestController
public class MobileCheckoutController implements MobileCheckoutApi {
    private final PrepareMobileCheckoutHandler prepare;
    private final CompleteMobileCheckoutHandler complete;
    public MobileCheckoutController(PrepareMobileCheckoutHandler prepare, CompleteMobileCheckoutHandler complete) {
        this.prepare = prepare;
        this.complete = complete;
    }
    @Override
    @PreAuthorize("isAuthenticated()")
    public ResponseEntity<PrepareMobileCheckoutHandler.Handoff> prepare(UUID orderId,
        OAuth2AuthenticatedPrincipal actor) {
        return ResponseEntity.ok()
            .cacheControl(CacheControl.noStore())
            .body(prepare.handle(new PrepareMobileCheckoutCommand(UUID.fromString(actor.getName()), orderId)));
    }
    @Override
    public ResponseEntity<String> landing(String token) {
        return page(
            "<h1>Continue your gift.</h1><p>Your payment is handled securely by the provider. After payment, close this browser and refresh Divine Community Space.</p><form method='post'><button type='submit'>Continue to secure payment</button></form>",
            "'self'");
    }
    @Override
    public ResponseEntity<String> complete(String token) {
        try {
            return completeVerified(token);
        } catch (PaymentException error) {
            if (error.kind() != PaymentException.Kind.MISSING) {
                throw error;
            }
            ResponseEntity<String> expired = page(
                "<h1>Let’s reopen your checkout.</h1><p>This secure link expired or is no longer available. Return to Divine Community Space, refresh your payment and open a fresh checkout link for the same gift.</p>",
                "'none'");
            return ResponseEntity.status(410).headers(expired.getHeaders()).body(expired.getBody());
        }
    }
    private ResponseEntity<String> completeVerified(String token) {
        PaymentGateway.Checkout checkout = complete.handle(new CompleteMobileCheckoutCommand(token));
        URI url = URI.create(checkout.url());
        if (!"https".equals(url.getScheme()) || url.getUserInfo() != null
            || url.getPort() != -1 && url.getPort() != 443) {
            throw PaymentException.unavailable("Provider checkout could not be verified.");
        }
        if ("c.yoco.com".equals(url.getHost()) && checkout.fields().isEmpty()) {
            return ResponseEntity.status(303)
                .cacheControl(CacheControl.noStore())
                .header("Location", url.toString())
                .header("Referrer-Policy", "no-referrer")
                .build();
        }
        if (!List.of("www.payfast.co.za", "sandbox.payfast.co.za").contains(url.getHost())
            || !"/eng/process".equals(url.getPath())) {
            throw PaymentException.unavailable("Provider checkout could not be verified.");
        }
        String fields = checkout.fields()
            .entrySet()
            .stream()
            .map(e -> "<input type='hidden' name='" + HtmlUtils.htmlEscape(e.getKey()) + "' value='"
                + HtmlUtils.htmlEscape(e.getValue()) + "'>")
            .collect(Collectors.joining());
        return page(
            "<h1>Your gift is ready.</h1><p>Continue to Payfast to enter your payment details.</p><form method='post' action='"
                + HtmlUtils.htmlEscape(url.toString()) + "'>" + fields
                + "<button type='submit'>Continue to Payfast</button></form>",
            url.getScheme() + "://" + url.getHost());
    }
    private ResponseEntity<String> page(String body, String formOrigin) {
        String html =
            "<!doctype html><html lang='en'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'><title>Divine Community Space · Secure giving</title><style>body{font:17px/1.6 system-ui;color:#183c34;background:#f6f8f4;margin:0;padding:48px 24px}main{max-width:480px;margin:8vh auto;background:white;padding:32px;border-radius:24px;border:1px solid #e1e8df}h1{line-height:1.15}button{background:#235846;color:white;border:0;border-radius:14px;padding:16px 22px;font:inherit;cursor:pointer;width:100%}small{letter-spacing:.18em;color:#718579}</style></head><body><main><small>DIVINE COMMUNITY SPACE</small>"
                + body + "</main></body></html>";
        return ResponseEntity.ok()
            .cacheControl(CacheControl.noStore())
            .header("Content-Type", "text/html;charset=UTF-8")
            .header("Referrer-Policy", "no-referrer")
            .header("Content-Security-Policy",
                "default-src 'none'; style-src 'unsafe-inline'; form-action " + formOrigin
                    + "; base-uri 'none'; frame-ancestors 'none'")
            .body(html);
    }
}
