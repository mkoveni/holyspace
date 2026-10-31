package org.onechurch.events.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_DEFINITIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_OCCURRENCES;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.EventDayIntegrationTestSupport;

import java.time.Instant;
import java.time.OffsetDateTime;
import java.util.UUID;
class EventAttendanceReportApiIntegrationTest extends EventDayIntegrationTestSupport {
    @Test
    void reportsSeparateBookingsArrivalsAndAbsenceAndExportSafeCsv() throws Exception {
        UUID occurrence = nearOccurrence(3), a = register(occurrence, person("Here"));
        register(occurrence, person("Absent"));
        created(arrive(a, UUID.randomUUID()));
        String range = "?from=" + Instant.now().minusSeconds(86400) + "&until=" + Instant.now().plusSeconds(86400);
        Response before = request("GET", ROOT + "/attendance-report" + range, admin, null);
        assertThat(before.status()).withFailMessage(before.body()).isEqualTo(200);
        assertThat(before.body()).contains("\"attended\":1", "\"absent\":0");
        db.update(EVENT_OCCURRENCES)
            .set(EVENT_OCCURRENCES.STARTS_AT, OffsetDateTime.now().minusHours(2))
            .set(EVENT_OCCURRENCES.ENDS_AT, OffsetDateTime.now().minusMinutes(1))
            .where(EVENT_OCCURRENCES.ID.eq(occurrence))
            .execute();
        db.update(EVENT_DEFINITIONS).set(EVENT_DEFINITIONS.TITLE, "=SUM(1,2)").execute();
        Response after = request("GET", ROOT + "/attendance-report" + range, admin, null);
        assertThat(after.body()).contains("\"attended\":1", "\"absent\":1");
        io.restassured.response.Response csv = io.restassured.RestAssured.given()
            .baseUri("http://localhost")
            .port(port)
            .header("Authorization", "Bearer " + admin)
            .get(ROOT + "/attendance-report.csv" + range);
        assertThat(csv.statusCode()).isEqualTo(200);
        assertThat(csv.contentType()).startsWith("text/csv");
        assertThat(csv.asString()).contains("\"'=SUM(1,2)\"").contains("Attended,Absent");
        assertThat(
            request("GET", ROOT + "/occurrences/" + occurrence + "/attendance?status=ABSENT", admin, null).body())
            .contains("Absent Mabasa")
            .doesNotContain("Here Mabasa");
    }
    @Test
    void reportRangesAndAttendancePermissionAreEnforced() throws Exception {
        assertThat(request("GET", ROOT + "/attendance-report?from=2099-01-01T00:00:00Z&until=2101-01-01T00:00:00Z",
            admin, null).status()).isEqualTo(400);
        assertThat(request("GET", ROOT + "/attendance-report?from=2099-01-01T00:00:00Z&until=2099-02-01T00:00:00Z",
            readerToken(), null).status()).isEqualTo(403);
    }
}
