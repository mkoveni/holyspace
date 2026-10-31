package org.onechurch.shared.infrastructure.operations;

import static org.hamcrest.Matchers.equalTo;
import static org.hamcrest.Matchers.hasKey;
import static org.hamcrest.Matchers.not;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.TestcontainersConfiguration;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.boot.test.web.server.LocalServerPort;
import org.springframework.context.annotation.Import;
import org.springframework.test.context.ActiveProfiles;

import io.restassured.RestAssured;
import io.restassured.specification.RequestSpecification;

@ActiveProfiles("production")
@Import(TestcontainersConfiguration.class)
@SpringBootTest(webEnvironment = SpringBootTest.WebEnvironment.RANDOM_PORT, properties = {
    "SECRETS_LOCATION=optional:configtree:/tmp/holyspace-production-test-secrets/", "spring.flyway.enabled=true",
    "onechurch.email.enabled=false"})
class ProductionBoundaryIntegrationTest {
    @LocalServerPort private int port;

    @Test
    void probesArePublicWithoutDatabaseDetailsOrOtherManagementEndpoints() {
        request().get("/actuator/health/readiness")
            .then()
            .statusCode(200)
            .body("status", equalTo("UP"))
            .body("$", not(hasKey("components")));
        request().get("/actuator/health/liveness").then().statusCode(200).body("status", equalTo("UP"));
        request().get("/actuator/env").then().statusCode(401);
        request().get("/api/me").then().statusCode(401);
    }

    @Test
    void productionDoesNotPublishApiDocumentation() {
        request().get("/v3/api-docs").then().statusCode(404);
        request().get("/docs").then().statusCode(404);
    }

    private RequestSpecification request() {
        return RestAssured.given().baseUri("http://127.0.0.1").port(port);
    }
}
