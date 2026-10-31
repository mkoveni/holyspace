package org.onechurch.payments.infrastructure.gateway;
import org.onechurch.payments.application.contract.YocoWebhookReader;
import org.onechurch.payments.application.contract.YocoWebhookWriter;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.ProviderCredentials;
import org.springframework.stereotype.Component;

import tools.jackson.databind.JsonNode;
import tools.jackson.databind.json.JsonMapper;

import java.util.ArrayList;
import java.util.List;
import java.util.Map;
@Component
public final class HostedYocoWebhooks implements YocoWebhookReader, YocoWebhookWriter {
    private static final String URL = "https://payments.yoco.com/api/webhooks";
    private final ProviderHttp http;
    private final JsonMapper json = JsonMapper.builder().build();
    public HostedYocoWebhooks(ProviderHttp http) {
        this.http = http;
    }
    private Map<String, String> headers(String key) {
        return Map.of("Authorization", "Bearer " + key, "Content-Type", "application/json");
    }
    public List<Entry> list(String key) {
        JsonNode result = json.readTree(http.send("GET", URL, headers(key), ""));
        if (!result.path("subscriptions").isArray() || result.path("subscriptions").size() > 100) {
            throw PaymentException.unavailable("Yoco webhook list could not be confirmed.");
        }
        List<Entry> entries = new ArrayList<>();
        for (JsonNode item : result.path("subscriptions")) {
            entries.add(new Entry(item.path("id").asText(), item.path("name").asText(), item.path("url").asText(),
                item.path("mode").asText()));
        }
        return List.copyOf(entries);
    }
    public Registered register(String key, String name, String callback, String mode) {
        JsonNode result = json.readTree(
            http.send("POST", URL, headers(key), json.writeValueAsString(Map.of("name", name, "url", callback))));
        String id = result.path("id").asText(), secret = result.path("secret").asText();
        if (!id.matches("sub_[A-Za-z0-9_-]{1,150}") || !result.path("url").asText().equals(callback)
            || !result.path("mode").asText().equals(mode)
            || !new ProviderCredentials(null, null, null, key, secret).complete("yoco", mode)) {
            throw PaymentException.unavailable(
                "Yoco registration could not be confirmed. Inspect the webhook list before registering again.");
        }
        return new Registered(id, secret);
    }
    public void remove(String key, String id) {
        if (!id.matches("sub_[A-Za-z0-9_-]{1,150}")) {
            throw PaymentException.invalid("Choose a valid Yoco subscription.");
        }
        http.send("DELETE", URL + "/" + id, headers(key), "");
    }
}
