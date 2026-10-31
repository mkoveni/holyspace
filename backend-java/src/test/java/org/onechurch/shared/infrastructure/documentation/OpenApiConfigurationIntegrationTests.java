package org.onechurch.shared.infrastructure.documentation;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;

import tools.jackson.databind.JsonNode;
import tools.jackson.databind.json.JsonMapper;

import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;

class OpenApiConfigurationIntegrationTests extends IdentityIntegrationTestSupport {

    @Test
    void specificationDocumentsAuthenticationSchemasAndActualResponseCodes() throws Exception {
        HttpResponse<String> response = getDocumentation("/v3/api-docs");
        assertThat(response.statusCode()).isEqualTo(200);
        JsonNode document = JsonMapper.builder().build().readTree(response.body());
        assertThat(document.path("openapi").asText()).startsWith("3.1.");
        assertThat(document.path("info").path("title").asText()).isEqualTo("OneChurch API");
        assertThat(document.path("security").get(0).has("bearerAuth")).isTrue();
        JsonNode components = document.path("components");
        assertThat(components.path("securitySchemes").path("bearerAuth").path("scheme").asText()).isEqualTo("bearer");
        JsonNode paths = document.path("paths");
        assertThat(paths.has("/actuator/health")).isFalse();
        assertThat(paths.path("/api/users").path("get").path("summary").asText()).isEqualTo("Search accounts");
        assertThat(paths.path("/api/users").path("get").path("tags").get(0).asText()).isEqualTo("Accounts");

        assertThat(paths.path("/api/login_check").path("post").path("security").isEmpty()).isTrue();
        assertThat(paths.path("/api/login_check").path("post").path("responses").path("200").has("content")).isTrue();
        assertThat(paths.path("/api/users").path("post").path("responses").has("201")).isTrue();
        assertThat(paths.path("/api/logout").path("post").path("responses").has("204")).isTrue();
        assertThat(paths.path("/api/logout").path("post").path("responses").has("200")).isFalse();
        assertThat(paths.path("/api/logout").path("post").path("responses").path("204").has("content")).isFalse();
        assertThat(paths.path("/api/users").path("get").path("responses").path("403").path("$ref").asText())
            .isEqualTo("#/components/responses/Forbidden");
        assertThat(
            components.path("schemas").path("Login").path("properties").path("password").path("writeOnly").asBoolean())
            .isTrue();
        assertThat(response.body()).doesNotContain("OAuth2AuthenticatedPrincipal", "passwordHash");
    }

    @Test
    void scalarReferenceAndYamlArePublicWhileBusinessEndpointsRemainProtected() throws Exception {
        HttpResponse<String> ui = getDocumentation("/docs");
        assertThat(ui.statusCode()).isEqualTo(200);
        assertThat(ui.headers().firstValue("Content-Type").orElseThrow()).contains("text/html");
        assertThat(ui.body()).contains("OneChurch API Reference", "/v3/api-docs", "scalar", "showDeveloperTools",
            "persistAuth", "disabled");

        HttpResponse<String> yaml = getDocumentation("/v3/api-docs.yaml");
        assertThat(yaml.statusCode()).isEqualTo(200);
        assertThat(yaml.body()).contains("openapi: 3.1.", "bearerAuth:", "/api/login_check:");
        assertThat(request("GET", "/api/me", null, null).status()).isEqualTo(401);
        assertThat(request("GET", "/api/users", null, null).status()).isEqualTo(401);
    }

    private HttpResponse<String> getDocumentation(String path) throws Exception {
        HttpRequest request = HttpRequest.newBuilder(URI.create("http://localhost:" + port + path)).GET().build();
        try (HttpClient client = HttpClient.newHttpClient()) {
            return client.send(request, HttpResponse.BodyHandlers.ofString());
        }
    }
}
