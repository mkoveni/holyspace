package org.onechurch.communications.presentation;
import static io.restassured.RestAssured.given;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_AUDIT;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.communications.application.contract.EmailTemplates;
import org.onechurch.shared.infrastructure.CommunicationsIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import io.restassured.specification.RequestSpecification;
import tools.jackson.databind.json.JsonMapper;

import java.util.List;
import java.util.Map;
import java.util.UUID;
class TemplateApiIntegrationTest extends CommunicationsIntegrationTestSupport {
    @Autowired private JsonMapper mapper;
    @Autowired private EmailTemplates templates;
    private RequestSpecification api() {
        return given().baseUri("http://localhost").port(port).auth().oauth2(admin).contentType("application/json");
    }
    private String json(Object value) {
        return mapper.writeValueAsString(value);
    }
    private Map<String, Object> draft(String key) {
        return Map.of("key", key, "name", "Welcome message", "subject", "Welcome {{name}}", "html",
            "<h2>Hello {{name}}</h2><p onclick='bad()'>Welcome to church.</p><script>bad()</script>", "variables",
            List.of("name"));
    }
    @Test
    void draftsPublishWithImmutableHistoryAndOptimisticConcurrency() {
        String key = "test." + UUID.randomUUID();
        String id = api().body(json(draft(key)))
            .post("/api/communications/templates")
            .then()
            .statusCode(201)
            .extract()
            .path("id");
        api().get("/api/communications/templates/" + id).then().statusCode(200);
        api().body(json(Map.of("expectedVersion", 0)))
            .post("/api/communications/templates/" + id + "/publish")
            .then()
            .statusCode(200);
        EmailTemplates.Rendered first = templates.render(key, Map.of("name", "Alex <script>"));
        assertThat(first.html()).contains("Alex &lt;script&gt;").doesNotContain("onclick", "<script>");
        Map<String, Object> edit = Map.of("expectedVersion", 1, "name", "Welcome message", "subject",
            "Updated {{name}}", "html", "<p>New wording for {{name}}</p>", "variables", List.of("name"));
        api().body(json(edit)).put("/api/communications/templates/" + id).then().statusCode(200);
        assertThat(templates.render(key, Map.of("name", "Alex <script>"))).isEqualTo(first);
        api().body(json(edit)).put("/api/communications/templates/" + id).then().statusCode(409);
        api().body(json(Map.of("expectedVersion", 2)))
            .post("/api/communications/templates/" + id + "/publish")
            .then()
            .statusCode(200);
        assertThat(templates.render(key, Map.of("name", "Alex")).subject()).isEqualTo("Updated Alex");
        assertThat(api().get("/api/communications/templates/" + id + "/revisions")
            .then()
            .statusCode(200)
            .extract()
            .jsonPath()
            .getList("$")).hasSize(2);
        api().body(json(Map.of("expectedVersion", 3)))
            .post("/api/communications/templates/" + id + "/archive")
            .then()
            .statusCode(200);
        api()
            .body(json(Map.of("expectedVersion", 4, "name", "Edit", "subject", "Hello", "html", "<p>Hello</p>",
                "variables", List.of())))
            .put("/api/communications/templates/" + id)
            .then()
            .statusCode(409);
    }
    @Test
    void previewIsReadOnlyAndRejectsUnsafeVariablePlacementAndBadSubstitutions() {
        int auditCount = db.fetchCount(COMMUNICATION_AUDIT);
        Map<String, Object> preview = Map.of("subject", "Hello {{name}}", "html", "<p>{{name}}</p>", "variables",
            List.of("name"), "values", Map.of("name", "<img src=x onerror=alert(1)>"));
        String rendered = api().body(json(preview))
            .post("/api/communications/templates/preview")
            .then()
            .statusCode(200)
            .extract()
            .path("html");
        assertThat(rendered).contains("&lt;img").doesNotContain("<img");
        assertThat(db.fetchCount(COMMUNICATION_AUDIT)).isEqualTo(auditCount);
        api()
            .body(json(Map.of("subject", "Hello", "html", "<p><a href='{{url}}'>Visit</a></p>", "variables",
                List.of("url"), "values", Map.of("url", "javascript:alert(1)"))))
            .post("/api/communications/templates/preview")
            .then()
            .statusCode(400);
        api()
            .body(json(Map.of("subject", "Hello {{name}}", "html", "<p>{{name}}</p>", "variables", List.of("name"),
                "values", Map.of())))
            .post("/api/communications/templates/preview")
            .then()
            .statusCode(400);
    }
    @Test
    void apiRequiresLiveReadAndManagementPermissions() {
        given().baseUri("http://localhost").port(port).get("/api/communications/templates").then().statusCode(401);
        db.deleteFrom(ROLE_PERMISSIONS)
            .where(ROLE_PERMISSIONS.PERMISSION_ID.in(db.select(PERMISSIONS.ID)
                .from(PERMISSIONS)
                .where(PERMISSIONS.CODE.eq("communications.templates.manage"))))
            .execute();
        api().get("/api/communications/templates").then().statusCode(200);
        api().body(json(draft("test." + UUID.randomUUID())))
            .post("/api/communications/templates")
            .then()
            .statusCode(403);
        db.deleteFrom(ROLE_PERMISSIONS)
            .where(ROLE_PERMISSIONS.PERMISSION_ID.in(db.select(PERMISSIONS.ID)
                .from(PERMISSIONS)
                .where(PERMISSIONS.CODE.eq("communications.templates.read"))))
            .execute();
        api().get("/api/communications/templates").then().statusCode(403);
    }
    @Test
    void templateKeysAreUniqueAndPagesAreBounded() {
        String key = "test." + UUID.randomUUID();
        api().body(json(draft(key))).post("/api/communications/templates").then().statusCode(201);
        api().body(json(draft(key))).post("/api/communications/templates").then().statusCode(409);
        api().get("/api/communications/templates?limit=101").then().statusCode(400);
        api().get("/api/communications/templates?offset=-1").then().statusCode(400);
    }
}
