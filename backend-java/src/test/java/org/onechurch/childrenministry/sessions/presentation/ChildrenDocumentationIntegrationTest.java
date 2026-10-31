package org.onechurch.childrenministry.sessions.presentation;
import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;

import tools.jackson.databind.JsonNode;
import tools.jackson.databind.json.JsonMapper;
class ChildrenDocumentationIntegrationTest extends IdentityIntegrationTestSupport {
    @Test
    void documentsChildrenContractsWithoutExposingInternalAttendanceFields() {
        io.restassured.response.Response response =
            io.restassured.RestAssured.given().baseUri("http://localhost").port(port).get("/v3/api-docs");
        assertThat(response.statusCode()).isEqualTo(200);
        JsonNode doc = JsonMapper.builder().build().readTree(response.asString());
        JsonNode schemas = doc.path("components").path("schemas");
        assertThat(doc.path("paths").has("/api/children/attendance/supervisor-checkout")).isTrue();
        assertThat(schemas.path("ChildrenCheckOutChildRequest")
            .path("properties")
            .path("pickupCode")
            .path("writeOnly")
            .asBoolean()).isTrue();
        assertThat(schemas.path("AttendanceView").path("properties").has("labelHash")).isFalse();
        assertThat(schemas.path("ChildrenSaveChildSupportRequest").path("properties").has("restrictedNotes")).isTrue();
        assertThat(response.asString()).doesNotContain("OAuth2AuthenticatedPrincipal", "labelHash");
    }
}
