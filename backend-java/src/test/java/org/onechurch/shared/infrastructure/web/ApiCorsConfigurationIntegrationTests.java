package org.onechurch.shared.infrastructure.web;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.ValueSource;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;

import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.util.Map;

class ApiCorsConfigurationIntegrationTests extends IdentityIntegrationTestSupport {

    @ParameterizedTest
    @ValueSource(strings = {"http://localhost:5173", "http://127.0.0.1:5173"})
    void allowedPreflightRunsBeforeAuthentication(String origin) throws Exception {
        HttpResponse<String> response = preflight(origin, "PUT", "authorization,content-type");

        assertThat(response.statusCode()).isEqualTo(200);
        assertThat(response.headers().firstValue("Access-Control-Allow-Origin")).contains(origin);
        assertThat(response.headers().firstValue("Access-Control-Allow-Methods").orElseThrow()).contains("GET", "POST",
            "PUT", "DELETE");
        assertThat(response.headers().firstValue("Access-Control-Allow-Headers").orElseThrow())
            .containsIgnoringCase("authorization")
            .containsIgnoringCase("content-type");
        assertThat(response.headers().firstValue("Access-Control-Max-Age")).contains("3600");
        assertThat(response.headers().firstValue("Access-Control-Allow-Credentials")).isEmpty();
    }

    @Test
    void rejectsUnlistedOriginsOnPreflightAndActualRequests() throws Exception {
        assertRejected(preflight("https://untrusted.example.org", "GET", "authorization"));
        assertRejected(send(HttpRequest.newBuilder(URI.create("http://localhost:" + port + "/api/users"))
            .header("Origin", "https://untrusted.example.org")
            .header("Authorization", "Bearer " + admin)
            .GET()
            .build()));
    }

    @Test
    void rejectsUnsupportedMethodsAndHeaders() throws Exception {
        assertRejected(preflight("http://localhost:5173", "TRACE", "authorization"));
        assertRejected(preflight("http://localhost:5173", "GET", "X-Unsupported-Header"));
    }

    @Test
    void actualCrossOriginRequestsStillRequireAuthenticationAndPermission() throws Exception {
        createUser("helper", "helper@church.org", "Helper");
        String helper = login("helper", PASSWORD);
        for (Map.Entry<String, Integer> request : Map.of("", 401, helper, 403, admin, 200).entrySet()) {
            HttpRequest.Builder builder = HttpRequest.newBuilder(URI.create("http://localhost:" + port + "/api/users"))
                .header("Origin", "http://localhost:5173");
            if (!request.getKey().isEmpty()) {
                builder.header("Authorization", "Bearer " + request.getKey());
            }
            HttpResponse<String> response = send(builder.GET().build());
            assertThat(response.statusCode()).isEqualTo(request.getValue());
            assertThat(response.headers().firstValue("Access-Control-Allow-Origin")).contains("http://localhost:5173");
            assertThat(response.headers().firstValue("Access-Control-Expose-Headers").orElseThrow())
                .contains("WWW-Authenticate", "Retry-After");
        }
    }

    private HttpResponse<String> preflight(String origin, String method, String headers) throws Exception {
        return send(HttpRequest.newBuilder(URI.create("http://localhost:" + port + "/api/users"))
            .header("Origin", origin)
            .header("Access-Control-Request-Method", method)
            .header("Access-Control-Request-Headers", headers)
            .method("OPTIONS", HttpRequest.BodyPublishers.noBody())
            .build());
    }

    private HttpResponse<String> send(HttpRequest request) throws Exception {
        try (HttpClient client = HttpClient.newHttpClient()) {
            return client.send(request, HttpResponse.BodyHandlers.ofString());
        }
    }

    private void assertRejected(HttpResponse<String> response) {
        assertThat(response.statusCode()).isEqualTo(403);
        assertThat(response.headers().firstValue("Access-Control-Allow-Origin")).isEmpty();
    }
}
