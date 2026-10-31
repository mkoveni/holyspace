package org.onechurch.payments.infrastructure.gateway;
import org.onechurch.payments.application.contract.ProviderConnectionProbe;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.ProviderConfiguration;
import org.onechurch.payments.domain.ProviderCredentials;
import org.springframework.security.web.util.matcher.IpAddressMatcher;
import org.springframework.stereotype.Component;

import tools.jackson.databind.JsonNode;
import tools.jackson.databind.json.JsonMapper;

import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.time.Clock;
import java.time.OffsetDateTime;
import java.util.HexFormat;
import java.util.Map;
import java.util.TreeMap;
@Component
public final class HostedProviderConnectionProbe implements ProviderConnectionProbe {
    private final ProviderHttp http;
    private final Clock clock;
    private final JsonMapper json = JsonMapper.builder().build();
    public HostedProviderConnectionProbe(ProviderHttp http, Clock clock) {
        this.http = http;
        this.clock = clock;
    }
    @Override
    public void validateNetworks(String value) {
        String[] ranges = value.split(",", -1);
        if (ranges.length > 30) {
            throw PaymentException.invalid("Use at most 30 trusted notification networks.");
        }
        for (String range : ranges) {
            String clean = range.trim();
            if (clean.isEmpty() || !clean.matches("[0-9a-fA-F:.]+(?:/[0-9]{1,3})?") || clean.endsWith("/0")) {
                throw PaymentException.invalid("Use explicit trusted IP addresses or restricted CIDR networks.");
            }
            if (clean.contains("/") && Integer.parseInt(clean.substring(clean.indexOf('/') + 1)) == 0) {
                throw PaymentException.invalid("A universal notification network is not allowed.");
            }
            try {
                new IpAddressMatcher(clean);
            } catch (IllegalArgumentException error) {
                throw PaymentException.invalid("A trusted notification network is invalid.");
            }
        }
    }
    @Override
    public Result check(ProviderConfiguration configuration, ProviderCredentials credentials) {
        if (configuration.provider().equals("payfast")) {
            Map<String, String> headers = new java.util.LinkedHashMap<>();
            headers.put("merchant-id", credentials.merchantId());
            headers.put("version", "v1");
            headers.put("timestamp", OffsetDateTime.now(clock).toString());
            Map<String, String> signed = new TreeMap<>(headers);
            signed.put("passphrase", credentials.passphrase());
            String input = signed.entrySet()
                .stream()
                .map(e -> encode(e.getKey()) + "=" + encode(e.getValue()))
                .collect(java.util.stream.Collectors.joining("&"));
            headers.put("signature", md5(input));
            String response = http.send("GET",
                "https://api.payfast.co.za/ping" + (configuration.mode().equals("test") ? "?testing=true" : ""),
                headers, "").trim().replace("\"", "");
            return new Result(response.equals("Payfast API") || response.equals("API V1"),
                response.equals("Payfast API") || response.equals("API V1")
                    ? "API authentication confirmed. Complete a hosted checkout and callback pilot before taking live payments."
                    : "Payfast did not confirm API authentication.");
        }
        JsonNode response = json.readTree(http.send("GET", "https://payments.yoco.com/api/webhooks",
            Map.of("Authorization", "Bearer " + credentials.yocoKey()), ""));
        boolean found = false;
        for (JsonNode subscription : response.path("subscriptions")) {
            if (subscription.path("url").asText().equals(configuration.backendUrl() + "/api/payments/webhooks/yoco")
                && subscription.path("mode").asText().equals(configuration.mode())) {
                found = true;
            }
        }
        return new Result(found, found
            ? "API access and callback registration confirmed. A callback pilot is still required to verify the webhook secret."
            : "API access confirmed, but no matching callback URL was registered for this environment.");
    }
    private static String encode(String s) {
        return URLEncoder.encode(s, StandardCharsets.UTF_8).replace("*", "%2A");
    }
    private static String md5(String value) {
        try {
            return HexFormat.of()
                .formatHex(MessageDigest.getInstance("MD5").digest(value.getBytes(StandardCharsets.UTF_8)));
        } catch (NoSuchAlgorithmException error) {
            throw new IllegalStateException(error);
        }
    }
}
