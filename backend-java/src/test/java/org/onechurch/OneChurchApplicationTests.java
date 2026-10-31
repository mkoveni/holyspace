package org.onechurch;

import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLES;

import org.flywaydb.core.Flyway;
import org.jooq.DSLContext;
import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.TestcontainersConfiguration;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.boot.test.web.server.LocalServerPort;
import org.springframework.context.annotation.Import;

import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.util.UUID;

@Import(TestcontainersConfiguration.class)
@SpringBootTest(webEnvironment = SpringBootTest.WebEnvironment.RANDOM_PORT)
class OneChurchApplicationTests {

    @Autowired private DSLContext database;

    @Autowired private Flyway flyway;

    @LocalServerPort private int port;

    @Test
    void flywayMigratesPostgresAndJooqReadsTheMigratedTable() {
        flyway.validate();
        assertThat(flyway.info().pending()).isEmpty();
        assertThat(flyway.info().current().getVersion().getVersion()).isEqualTo("30");
        String name = database.select(ROLES.NAME)
            .from(ROLES)
            .where(ROLES.ID.eq(UUID.fromString("00000000-0000-0000-0000-000000000001")))
            .fetchSingle()
            .value1();
        assertThat(name).isEqualTo("administrator");
    }

    @Test
    void healthEndpointReportsUpWithPostgresAvailable() throws Exception {
        try (HttpClient client = HttpClient.newHttpClient()) {
            HttpRequest request =
                HttpRequest.newBuilder(URI.create("http://localhost:" + port + "/actuator/health")).GET().build();
            HttpResponse<String> response = client.send(request, HttpResponse.BodyHandlers.ofString());
            assertThat(response.statusCode()).isEqualTo(200);
            assertThat(response.body()).contains("\"status\":\"UP\"");
        }
    }
}
