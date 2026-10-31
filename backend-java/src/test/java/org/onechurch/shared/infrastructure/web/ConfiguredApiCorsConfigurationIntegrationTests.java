package org.onechurch.shared.infrastructure.web;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.TestcontainersConfiguration;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.boot.test.web.server.LocalServerPort;
import org.springframework.context.annotation.Import;

import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;

@Import(TestcontainersConfiguration.class)
@SpringBootTest(webEnvironment = SpringBootTest.WebEnvironment.RANDOM_PORT, properties = "onechurch.security.cors.allowed-origins=https://portal.example.org")
class ConfiguredApiCorsConfigurationIntegrationTests {

    @LocalServerPort private int port;

    @Test
    void configuredOriginsReplaceDevelopmentDefaults() throws Exception {
        try (HttpClient client = HttpClient.newHttpClient()) {
            HttpResponse<String> allowed =
                client.send(preflight("https://portal.example.org"), HttpResponse.BodyHandlers.ofString());
            assertThat(allowed.statusCode()).isEqualTo(200);
            assertThat(allowed.headers().firstValue("Access-Control-Allow-Origin"))
                .contains("https://portal.example.org");

            HttpResponse<String> rejected =
                client.send(preflight("http://localhost:5173"), HttpResponse.BodyHandlers.ofString());
            assertThat(rejected.statusCode()).isEqualTo(403);
            assertThat(rejected.headers().firstValue("Access-Control-Allow-Origin")).isEmpty();
        }
    }

    private HttpRequest preflight(String origin) {
        return HttpRequest.newBuilder(URI.create("http://localhost:" + port + "/api/users"))
            .header("Origin", origin)
            .header("Access-Control-Request-Method", "GET")
            .header("Access-Control-Request-Headers", "authorization")
            .method("OPTIONS", HttpRequest.BodyPublishers.noBody())
            .build();
    }
}
