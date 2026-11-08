package org.onechurch.communications.infrastructure;
import org.onechurch.communications.application.contract.PushTransport;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.stereotype.Component;

import tools.jackson.databind.JsonNode;
import tools.jackson.databind.json.JsonMapper;

import java.io.InputStream;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.time.Duration;
import java.util.List;
import java.util.Map;
import java.util.Set;
import java.util.UUID;
@Component
public final class ExpoPushTransport implements PushTransport {
    private final URI endpoint;
    private final String accessToken;
    private final JsonMapper json;
    private final HttpClient http = HttpClient.newBuilder()
        .connectTimeout(Duration.ofSeconds(5))
        .followRedirects(HttpClient.Redirect.NEVER)
        .build();
    public ExpoPushTransport(@Value("${PUSH_EXPO_URL:https://exp.host/--/api/v2/push}") String endpoint,
        @Value("${PUSH_ACCESS_TOKEN:}") String accessToken, JsonMapper json) {
        this.endpoint = URI.create(endpoint);
        this.accessToken = accessToken;
        this.json = json;
        if (!this.endpoint.getScheme().equals("https") && !(this.endpoint.getScheme().equals("http")
            && Set.of("127.0.0.1", "localhost").contains(this.endpoint.getHost()))) {
            throw new IllegalArgumentException("Use HTTPS for Expo push.");
        }
    }
    public Result send(String token, UUID notificationId) {
        return request("send",
            Map.of("to", token, "title", "Divine Community Space", "body", "You have a new update in your inbox.", "sound",
                "default", "channelId", "member-updates", "ttl", 3600, "data",
                Map.of("type", "divinecommunityspace.member-update.v1", "notificationId", notificationId.toString())),
            null);
    }
    public Result receipt(String ticketId) {
        return request("getReceipts", Map.of("ids", List.of(ticketId)), ticketId);
    }
    private Result request(String operation, Object body, String ticket) {
        boolean receipt = ticket != null;
        try {
            HttpRequest.Builder builder = HttpRequest.newBuilder(URI.create(endpoint.toString() + "/" + operation))
                .timeout(Duration.ofSeconds(10))
                .header("Content-Type", "application/json")
                .header("Accept", "application/json");
            if (!accessToken.isBlank()) {
                builder.header("Authorization", "Bearer " + accessToken);
            }
            HttpResponse<InputStream> response =
                http.send(builder.POST(HttpRequest.BodyPublishers.ofString(json.writeValueAsString(body))).build(),
                    HttpResponse.BodyHandlers.ofInputStream());
            try (InputStream stream = response.body()) {
                if (response.statusCode() == 429) {
                    return new Result("RETRY", ticket, "RATE_LIMITED");
                }
                if (response.statusCode() >= 500) {
                    return new Result(receipt ? "RETRY" : "UNKNOWN", ticket, "PROVIDER_UNCERTAIN");
                }
                if (response.statusCode() != 200) {
                    return new Result("FAILED", ticket, "PROVIDER_REJECTED");
                }
                byte[] payload = stream.readNBytes(65537);
                if (payload.length > 65536) {
                    return new Result(receipt ? "PENDING" : "UNKNOWN", ticket, "INVALID_RESPONSE");
                }
                JsonNode data = json.readTree(payload).path("data");
                if (receipt) {
                    data = data.path(ticket);
                    if (data.isMissingNode()) {
                        return new Result("PENDING", ticket, "");
                    }
                }
                String status = data.path("status").asString("");
                if (status.equals("ok")) {
                    if (receipt) {
                        return new Result("PROVIDER_ACCEPTED", ticket, "");
                    }
                    String id = data.path("id").asString("");
                    if (id.matches("[A-Za-z0-9_-]{1,200}")) {
                        return new Result("ACCEPTED", id, "");
                    }
                }
                String error = data.path("details").path("error").asString("");
                if (error.equals("MessageRateExceeded")) {
                    return new Result("RETRY", ticket, error);
                }
                if (error.equals("DeviceNotRegistered")) {
                    return new Result("FAILED", ticket, error);
                }
                return new Result(status.equals("error") ? "FAILED" : receipt ? "PENDING" : "UNKNOWN", ticket,
                    "PROVIDER_REJECTED");
            }
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
            return new Result(receipt ? "RETRY" : "UNKNOWN", ticket, "TRANSPORT_UNCERTAIN");
        } catch (Exception e) {
            return new Result(receipt ? "RETRY" : "UNKNOWN", ticket, "TRANSPORT_UNCERTAIN");
        }
    }
}
