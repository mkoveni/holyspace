package org.onechurch.people.presentation;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.PeopleIntegrationTestSupport;

import tools.jackson.databind.JsonNode;
import tools.jackson.databind.json.JsonMapper;

import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;

class PeopleDocumentationTest extends PeopleIntegrationTestSupport {
    @Test
    void documentsThePeopleInterfacesWithDistinctTransportSchemasAndNoPrincipalLeak() throws Exception {
        try (HttpClient client = HttpClient.newHttpClient()) {
            HttpResponse<String> response = client.send(
                HttpRequest.newBuilder(URI.create("http://localhost:" + port + "/v3/api-docs")).GET().build(),
                HttpResponse.BodyHandlers.ofString());
            assertThat(response.statusCode()).isEqualTo(200);
            JsonNode document = JsonMapper.builder().build().readTree(response.body());
            JsonNode paths = document.path("paths");
            assertThat(paths.path("/api/people").path("post").path("responses").has("201")).isTrue();
            assertThat(paths.path("/api/people/{id}").path("put").path("responses").has("204")).isTrue();
            assertThat(paths.path("/api/households/{id}/members/{membershipId}/end").has("post")).isTrue();
            assertThat(paths.path("/api/relationships").path("post").path("responses").has("409")).isTrue();
            JsonNode schemas = document.path("components").path("schemas");
            assertThat(schemas.path("HouseholdCreateRequest").path("properties").has("name")).isTrue();
            assertThat(schemas.path("RelationshipCreateRequest").path("properties").has("sourceId")).isTrue();
            assertThat(schemas.path("PeopleProfileRequest").path("properties").has("membershipType")).isTrue();
            assertThat(schemas.path("Profile").path("properties").has("email")).isTrue();
            assertThat(response.body()).doesNotContain("OAuth2AuthenticatedPrincipal");
        }
    }
}
