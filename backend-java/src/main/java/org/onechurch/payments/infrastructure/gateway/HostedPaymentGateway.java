package org.onechurch.payments.infrastructure.gateway;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.PaymentOrder;
import org.springframework.security.web.util.matcher.IpAddressMatcher;
import org.springframework.stereotype.Component;

import tools.jackson.databind.JsonNode;
import tools.jackson.databind.json.JsonMapper;

import java.net.URI;
import java.net.URLDecoder;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.security.GeneralSecurityException;
import java.security.MessageDigest;
import java.time.Clock;
import java.time.LocalDate;
import java.time.OffsetDateTime;
import java.time.ZoneId;
import java.util.Arrays;
import java.util.Base64;
import java.util.HexFormat;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.TreeMap;
import java.util.UUID;

import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
@Component
public final class HostedPaymentGateway implements PaymentGateway {
    private final PaymentSettings settings;
    private final StoredProviderConfigurations configurations;
    private final Clock clock;
    private final ZoneId zone;
    private final ProviderHttp http;
    private final JsonMapper json = JsonMapper.builder().build();
    public HostedPaymentGateway(PaymentSettings settings, Clock clock, ZoneId zone, ProviderHttp http) {
        this(settings, clock, zone, http, null);
    }
    @org.springframework.beans.factory.annotation.Autowired
    public HostedPaymentGateway(PaymentSettings settings, Clock clock, ZoneId zone, ProviderHttp http,
        StoredProviderConfigurations configurations) {
        this.settings = settings;
        this.clock = clock;
        this.zone = zone;
        this.http = http;
        this.configurations = configurations;
    }
    private HostedPaymentGateway bound(PaymentSettings configuration) {
        return new HostedPaymentGateway(configuration, clock, zone, http);
    }
    @Override
    public List<Provider> providers() {
        if (configurations != null) {
            return java.util.List.of("payfast", "yoco").stream().map(id -> {
                StoredProviderConfigurations.Resolved resolved = configurations.current(id);
                return new Provider(id, id.equals("payfast") ? "Payfast" : "Yoco", resolved.available(),
                    id.equals("payfast") ? resolved.settings().payfastMode : resolved.settings().yocoMode,
                    resolved.monthly());
            }).toList();
        }
        return List.of(new Provider("payfast", "Payfast", settings.available("payfast"), settings.payfastMode, true),
            new Provider("yoco", "Yoco", settings.available("yoco"), settings.yocoMode, false));
    }
    @Override
    public String mode(String provider) {
        if (configurations != null) {
            StoredProviderConfigurations.Resolved resolved = configurations.current(provider);
            if (!resolved.available()) {
                throw PaymentException
                    .unavailable("This payment provider is disabled or not configured. Ask the church administrator.");
            }
            return provider.equals("payfast") ? resolved.settings().payfastMode : resolved.settings().yocoMode;
        }
        if (!settings.available(provider)) {
            throw PaymentException
                .unavailable("This payment provider is not configured. Ask the church administrator.");
        }
        return provider.equals("payfast") ? settings.payfastMode : settings.yocoMode;
    }
    @Override
    public void validateOrder(PaymentOrder order) {
        if (configurations != null) {
            PaymentSettings original = configurations.forOrder(order);
            if (!original.available(order.provider())) {
                throw PaymentException.unavailable("The original payment connection is unavailable.");
            }
        }
    }
    @Override
    public Checkout checkout(PaymentOrder order, String name, String email) {
        if (configurations != null) {
            return bound(configurations.forOrder(order)).checkout(order, name, email);
        }
        if (!mode(order.provider()).equals(order.mode())) {
            throw PaymentException
                .conflict("Provider environment changed; review this checkout before creating another.");
        }
        String returned =
            settings.base() + (order.selfService() ? "/my-giving?order=" : "/payments?order=") + order.id();
        if (order.provider().equals("payfast")) {
            Map<String, String> fields = new LinkedHashMap<>();
            fields.put("merchant_id", settings.merchantId);
            fields.put("merchant_key", settings.merchantKey);
            fields.put("return_url", returned);
            fields.put("cancel_url", returned + "&returned=cancelled");
            fields.put("notify_url", settings.backendBase() + "/api/payments/webhooks/payfast");
            fields.put("name_first", name.substring(0, Math.min(100, name.length())));
            if (!email.isBlank()) {
                fields.put("email_address", email);
            }
            fields.put("m_payment_id", order.id().toString());
            fields.put("amount", decimal(order.amountMinor()));
            fields.put("item_name", "Church giving");
            if (order.recurring()) {
                LocalDate now = LocalDate.now(clock.withZone(zone));
                LocalDate next = now.plusMonths(1);
                fields.put("subscription_type", "1");
                fields.put("billing_date", next.toString());
                fields.put("recurring_amount", decimal(order.amountMinor()));
                fields.put("frequency", "3");
                fields.put("cycles", "0");
            }
            fields.put("signature", formSignature(fields, settings.passphrase));
            return new Checkout(order.id().toString(),
                order.mode().equals("test") ? "https://sandbox.payfast.co.za/eng/process"
                    : "https://www.payfast.co.za/eng/process",
                java.util.Collections.unmodifiableMap(new LinkedHashMap<>(fields)));
        }
        Map<String, Object> body = Map.of("amount", order.amountMinor(), "currency", "ZAR", "successUrl", returned,
            "cancelUrl", returned + "&returned=cancelled", "failureUrl", returned + "&returned=failed", "metadata",
            Map.of("orderId", order.id().toString()), "externalId", order.id().toString());
        JsonNode response = json.readTree(http.send(
            "POST", "https://payments.yoco.com/api/checkouts", Map.of("Authorization", "Bearer " + settings.yocoKey,
                "Content-Type", "application/json", "Idempotency-Key", order.id().toString()),
            json.writeValueAsString(body)));
        if (!response.path("amount").canConvertToLong() || response.path("amount").longValue() != order.amountMinor()
            || !response.path("currency").asText().equals("ZAR")
            || !response.path("processingMode").asText().equals(order.mode())
            || !validYocoUrl(response.path("redirectUrl").asText())) {
            throw PaymentException
                .unavailable("Yoco checkout could not be confirmed. Retry this same payment request.");
        }
        return new Checkout(reference(response.path("id").asText()), response.path("redirectUrl").asText(), Map.of());
    }
    @Override
    public Notification verify(String provider, String body, Map<String, String> headers, String ip) {
        if (body == null || body.length() > 65536) {
            throw PaymentException.invalid("Payment notification is too large.");
        }
        if (configurations != null) {
            return bound(configurations.forNotification(provider, body)).verify(provider, body, headers, ip);
        }
        String mode = mode(provider);
        try {
            return provider.equals("payfast") ? payfast(body, ip, mode) : yoco(body, headers, mode);
        } catch (PaymentException error) {
            throw error;
        } catch (RuntimeException error) {
            throw PaymentException.invalid("Invalid payment notification.");
        }
    }
    private Notification yoco(String body, Map<String, String> headers, String mode) {
        String id = headers.getOrDefault("webhook-id", ""), timestamp = headers.getOrDefault("webhook-timestamp", "");
        if (!id.matches("[A-Za-z0-9_-]{1,200}") || !timestamp.matches("[0-9]{1,12}")
            || Math.abs(clock.instant().getEpochSecond() - Long.parseLong(timestamp)) > 180) {
            throw PaymentException.invalid("Invalid payment notification timestamp.");
        }
        byte[] secret = Base64.getDecoder().decode(settings.yocoWebhookSecret.substring(6));
        if (secret.length < 16) {
            throw PaymentException.unavailable("Webhook verification is not configured.");
        }
        String expected = hmac(secret, id + "." + timestamp + "." + body);
        boolean valid = Arrays.stream(headers.getOrDefault("webhook-signature", "").split(" "))
            .filter(s -> s.startsWith("v1,"))
            .anyMatch(s -> constant(expected, s.substring(3)));
        if (!valid) {
            throw PaymentException.invalid("Invalid payment notification signature.");
        }
        JsonNode event = json.readTree(body);
        String type = switch (event.path("type").asText()) {
            case "payment.succeeded" -> "PAID";
            case "payment.failed" -> "FAILED";
            case "refund.succeeded" -> "REFUNDED";
            case "refund.failed" -> "REFUND_FAILED";
            default -> null;
        };
        if (type == null) {
            return null;
        }
        JsonNode p = event.path("payload");
        boolean success = type.equals("PAID") || type.equals("REFUNDED");
        if (!p.path("amount").isIntegralNumber() || !p.path("amount").canConvertToLong()
            || p.path("amount").longValue() < 1 || p.path("amount").longValue() > 100000000
            || !p.path("mode").asText().equals(mode)
            || !p.path("status").asText().equals(success ? "succeeded" : "failed")) {
            throw PaymentException.invalid("Payment notification has invalid amount, environment or status.");
        }
        String date = p.path("createdDate").asText(event.path("createdDate").asText(""));
        LocalDate received = date.isBlank() ? LocalDate.now(clock.withZone(zone))
            : OffsetDateTime.parse(date).atZoneSameInstant(zone).toLocalDate();
        if (received.isAfter(LocalDate.now(clock.withZone(zone)))) {
            throw PaymentException.invalid("Provider transaction date is in the future.");
        }
        String order = p.path("metadata").path("orderId").asText("");
        return new Notification("yoco", mode, reference(event.path("id").asText()), type,
            reference(p.path("id").asText()), reference(p.path("metadata").path("checkoutId").asText()), uuid(order),
            p.path("amount").longValue(), p.path("currency").asText(), null, received);
    }
    private Notification payfast(String body, String ip, String mode) {
        if (Arrays.stream(settings.ipRanges.split(","))
            .map(String::trim)
            .filter(s -> !s.isBlank())
            .noneMatch(range -> new IpAddressMatcher(range).matches(ip))) {
            throw PaymentException.invalid("Payment notification origin is not allowed.");
        }
        Map<String, String> data = new LinkedHashMap<>();
        for (String pair : body.split("&", -1)) {
            String[] part = pair.split("=", 2);
            String key = URLDecoder.decode(part[0], StandardCharsets.UTF_8);
            if (part.length != 2 || !key.matches("[A-Za-z0-9_]+") || data.containsKey(key)) {
                throw PaymentException.invalid("Invalid payment notification fields.");
            }
            data.put(key, URLDecoder.decode(part[1], StandardCharsets.UTF_8));
        }
        String signature = data.remove("signature"), encoded = form(data);
        if (signature == null || !constant(md5(encoded + "&passphrase=" + encode(settings.passphrase)), signature)
            || !settings.merchantId.equals(data.get("merchant_id"))) {
            throw PaymentException.invalid("Invalid payment signature or merchant.");
        }
        String valid = http.send("POST",
            mode.equals("test") ? "https://sandbox.payfast.co.za/eng/query/validate"
                : "https://www.payfast.co.za/eng/query/validate",
            Map.of("Content-Type", "application/x-www-form-urlencoded"), encoded);
        if (!valid.trim().equals("VALID")) {
            throw PaymentException.invalid("Payfast could not verify the notification.");
        }
        String type = switch (data.getOrDefault("payment_status", "")) {
            case "COMPLETE" -> "PAID";
            case "FAILED", "CANCELLED" -> "FAILED";
            default -> null;
        };
        if (type == null) {
            return null;
        }
        String amount = data.getOrDefault("amount_gross", "");
        if (!amount.matches("[0-9]{1,9}\\.[0-9]{2}")) {
            throw PaymentException.invalid("Invalid payment amount.");
        }
        long minor = new java.math.BigDecimal(amount).movePointRight(2).longValueExact();
        if (minor < 1 || minor > 100000000) {
            throw PaymentException.invalid("Invalid payment amount.");
        }
        String token = data.get("token");
        if (token != null && !token.matches("[a-fA-F0-9-]{36}")) {
            throw PaymentException.invalid("Invalid subscription token.");
        }
        String reference = reference(data.get("pf_payment_id"));
        return new Notification("payfast", mode, reference + ":" + type, type, reference, null,
            uuid(data.get("m_payment_id")), minor, "ZAR", token, null);
    }
    @Override
    public String subscription(PaymentOrder order, String token, boolean cancel) {
        if (configurations != null) {
            return bound(configurations.forOrder(order)).subscription(order, token, cancel);
        }
        if (!order.provider().equals("payfast") || !mode("payfast").equals(order.mode())
            || !token.matches("[a-fA-F0-9-]{36}")) {
            throw PaymentException.conflict("Subscription configuration does not match the original payment.");
        }
        if (cancel) {
            JsonNode result = api(order, token, "cancel", "PUT");
            if (!result.path("data").path("response").asBoolean(false)) {
                throw PaymentException
                    .unavailable("Subscription cancellation is unconfirmed; check the provider before retrying.");
            }
            return "CANCELLED";
        }
        String status = api(order, token, "fetch", "GET").path("data")
            .path("response")
            .path("status_text")
            .asText()
            .toUpperCase(java.util.Locale.ROOT);
        if (!List.of("ACTIVE", "PAUSED", "LOCKED", "CANCELLED").contains(status)) {
            throw PaymentException.unavailable("Provider subscription status is not recognised.");
        }
        return status;
    }
    private JsonNode api(PaymentOrder order, String token, String action, String method) {
        Map<String, String> headers = new LinkedHashMap<>();
        headers.put("merchant-id", settings.merchantId);
        headers.put("version", "v1");
        headers.put("timestamp", OffsetDateTime.now(clock).toString());
        Map<String, String> sign = new TreeMap<>(headers);
        sign.put("passphrase", settings.passphrase);
        headers.put("signature", md5(form(sign)));
        JsonNode result = json.readTree(http.send(method, "https://api.payfast.co.za/subscriptions/" + token + "/"
            + action + (order.mode().equals("test") ? "?testing=true" : ""), headers, ""));
        if (!result.path("status").asText().equals("success") || result.path("code").asInt() != 200) {
            throw PaymentException.unavailable("Provider subscription request is unconfirmed.");
        }
        return result;
    }
    static String formSignature(Map<String, String> fields, String passphrase) {
        Map<String, String> sign = new LinkedHashMap<>();
        fields.forEach((k, v) -> {
            if (v != null && !v.isEmpty()) {
                sign.put(k, v.trim());
            }
        });
        return md5(form(sign) + "&passphrase=" + encode(passphrase.trim()));
    }
    private static String form(Map<String, String> fields) {
        return fields.entrySet()
            .stream()
            .map(e -> encode(e.getKey()) + "=" + encode(e.getValue()))
            .collect(java.util.stream.Collectors.joining("&"));
    }
    private static String encode(String value) {
        return URLEncoder.encode(value, StandardCharsets.UTF_8).replace("*", "%2A").replace("%7E", "%7E");
    }
    private static String md5(String value) {
        try {
            return HexFormat.of()
                .formatHex(MessageDigest.getInstance("MD5").digest(value.getBytes(StandardCharsets.UTF_8)));
        } catch (GeneralSecurityException e) {
            throw new IllegalStateException(e);
        }
    }
    private static String hmac(byte[] secret, String value) {
        try {
            Mac mac = Mac.getInstance("HmacSHA256");
            mac.init(new SecretKeySpec(secret, "HmacSHA256"));
            return Base64.getEncoder().encodeToString(mac.doFinal(value.getBytes(StandardCharsets.UTF_8)));
        } catch (GeneralSecurityException e) {
            throw new IllegalStateException(e);
        }
    }
    private static boolean constant(String a, String b) {
        return MessageDigest.isEqual(a.getBytes(StandardCharsets.UTF_8), b.getBytes(StandardCharsets.UTF_8));
    }
    private static UUID uuid(String value) {
        return value == null || value.isBlank() ? null : UUID.fromString(value);
    }
    private static String reference(String value) {
        if (value == null || !value.matches("[A-Za-z0-9_-]{1,150}")) {
            throw PaymentException.invalid("Invalid provider reference.");
        }
        return value;
    }
    static boolean validYocoUrl(String value) {
        try {
            URI uri = URI.create(value);
            return "https".equals(uri.getScheme()) && "c.yoco.com".equals(uri.getHost()) && uri.getUserInfo() == null
                && (uri.getPort() == -1 || uri.getPort() == 443);
        } catch (IllegalArgumentException e) {
            return false;
        }
    }
    private static String decimal(long minor) {
        return minor / 100 + "." + String.format(java.util.Locale.ROOT, "%02d", minor % 100);
    }
}
