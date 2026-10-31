package org.onechurch.childrenministry.reporting.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ATTENDANCE_SEGMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import io.restassured.RestAssured;
import tools.jackson.databind.json.JsonMapper;

import java.time.ZoneId;
import java.time.ZoneOffset;
import java.util.UUID;
class AttendanceReportingApiIntegrationTest extends ChildrenIntegrationTestSupport {
    @Autowired private JsonMapper reportJson;
    @Test
    void reportsFilterBeforePagingAndExportEveryMatchingVisit() throws Exception {
        UUID first = attendanceId(arrival());
        Response issued = label(first, 0, "");
        assertThat(checkout(first, 1, (String) issued.data().get("pickupCode"), true, UUID.randomUUID()).status())
            .isEqualTo(200);
        arrival();
        Response report =
            call("GET", path() + "&limit=1&search=child&programmeId=" + programme + "&locationId=" + location, null);
        assertThat(report.status()).isEqualTo(200);
        assertThat(reportJson.readTree(report.body()).path("visits").size()).isEqualTo(1);
        assertThat(reportJson.readTree(report.body()).path("summary").path("visits").asInt()).isEqualTo(2);
        assertThat(reportJson.readTree(report.body()).path("summary").path("children").asInt()).isEqualTo(1);
        assertThat(report.body()).doesNotContain("labelHash", "pickupCode", "evidenceReference", "collectorId",
            "exceptionReason");
        assertThat(reportJson.readTree(call("GET", path() + "&search=missing", null).body())
            .path("summary")
            .path("visits")
            .asInt()).isZero();
        assertThat(reportJson.readTree(call("GET", path() + "&childId=" + UUID.randomUUID(), null).body())
            .path("summary")
            .path("visits")
            .asInt()).isZero();
        assertThat(reportJson.readTree(call("GET", path() + "&outcome=COLLECTED", null).body())
            .path("summary")
            .path("visits")
            .asInt()).isEqualTo(1);
        io.restassured.response.Response csv = RestAssured.given()
            .baseUri("http://localhost")
            .port(port)
            .header("Authorization", "Bearer " + admin)
            .accept("text/csv")
            .get("/api/children/attendance-reports/export?" + dates());
        assertThat(csv.statusCode()).isEqualTo(200);
        assertThat(csv.header("Content-Disposition")).isEqualTo("attachment; filename=\"children-attendance.csv\"");
        assertThat(csv.header("Cache-Control")).isEqualTo("no-store");
        assertThat(csv.asString().lines().count()).isEqualTo(3);
        assertThat(csv.asString()).contains("Child Mabasa", "Africa/Johannesburg", "COLLECTED", "ATTENDING")
            .doesNotContain("pickupCode", "evidenceReference", "labelHash");
    }
    @Test
    void dateBoundsUseChurchMidnightAndInclusiveLastDay() throws Exception {
        UUID visit = attendanceId(arrival());
        java.time.Instant midnight = today.atStartOfDay(ZoneId.of("Africa/Johannesburg")).toInstant();
        setArrival(visit, midnight.minusNanos(1000));
        assertVisits(0);
        setArrival(visit, midnight);
        assertVisits(1);
        setArrival(visit, midnight.plusSeconds(86400).minusNanos(1000));
        assertVisits(1);
        setArrival(visit, midnight.plusSeconds(86400));
        assertVisits(0);
    }
    @Test
    void supervisorCollectionsAreClearlyDistinguished() throws Exception {
        UUID visit = attendanceId(arrival());
        assertThat(call("POST", "/attendance/supervisor-checkout",
            body("attendanceId", visit, "expectedVersion", 0, "requestId", UUID.randomUUID(), "collectorId", collector,
                "identityVerified", true, "reason", "Private handover reason"))
            .status()).isEqualTo(200);
        Response report = call("GET", path() + "&outcome=SUPERVISOR", null);
        assertThat(report.body()).contains("\"supervisorCollection\":true").doesNotContain("Private handover reason");
        assertThat(reportJson.readTree(report.body()).path("summary").path("supervisorCollections").asInt())
            .isEqualTo(1);
    }
    @Test
    void readAndExportPermissionsAreEnforcedAndInvalidReportsRejected() throws Exception {
        assertThat(request("GET", "/api/children" + path(), null, null).status()).isEqualTo(401);
        assertThat(call("GET", "/attendance/" + UUID.randomUUID() + "/timeline", null).status()).isEqualTo(404);
        assertThat(call("GET", path() + "&limit=101", null).status()).isEqualTo(400);
        assertThat(call("GET", path() + "&outcome=INVALID", null).status()).isEqualTo(400);
        assertThat(call("GET", "/attendance-reports?from=" + today + "&until=" + today.minusDays(1), null).status())
            .isEqualTo(400);
        assertThat(call("GET", "/attendance-reports?from=" + today.minusDays(366) + "&until=" + today, null).status())
            .isEqualTo(400);
        UUID permission = db.select(PERMISSIONS.ID)
            .from(PERMISSIONS)
            .where(PERMISSIONS.CODE.eq("children.manage"))
            .fetchOne(PERMISSIONS.ID);
        db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.eq(permission)).execute();
        assertThat(call("GET", path(), null).status()).isEqualTo(200);
        assertThat(RestAssured.given()
            .baseUri("http://localhost")
            .port(port)
            .header("Authorization", "Bearer " + admin)
            .accept("text/csv")
            .get("/api/children/attendance-reports/export?" + dates())
            .statusCode()).isEqualTo(403);
    }
    @Test
    void transfersRemainInReportsAndJourneyAcrossProgrammeAndDateFilters() throws Exception {
        UUID visit = attendanceId(arrival());
        UUID nextGroup = create("/classes",
            body("programmeId", programme, "name", "Next class", "minAgeMonths", 60, "maxAgeMonths", 156));
        UUID nextRoom = create("/rooms", body("locationId", location, "name", "Room B", "capacity", 10));
        UUID next = newSession(nextGroup, nextRoom, 2);
        worker = datedPerson("Second worker", today.minusYears(30));
        UUID account = createUser("worker2", "worker2@church.org", "Worker Two");
        ok("POST", "/workers", body("personId", worker, "accountId", account, "screenedUntil", until, "trainedUntil",
            until, "evidenceReference", "verified/second"));
        open(next);
        ok("POST", "/class-assignments/end", body("assignmentId", assignment, "expectedVersion", 0));
        assign(child, enrolment, nextGroup);
        assertThat(call("POST", "/attendance/transfers",
            body("attendanceId", visit, "expectedVersion", 0, "targetSessionId", next, "reason", "Move class"))
            .status()).isEqualTo(200);
        Response issued = label(visit, 1, "After transfer");
        assertThat(checkout(visit, 2, (String) issued.data().get("pickupCode"), true, UUID.randomUUID()).status())
            .isEqualTo(200);
        Response report = call("GET", path() + "&childId=" + child, null);
        assertThat(reportJson.readTree(report.body()).path("summary").path("transferred").asInt()).isEqualTo(1);
        assertThat(reportJson.readTree(report.body()).path("summary").path("collected").asInt()).isEqualTo(1);
        Response timeline = call("GET", "/attendance/" + visit + "/timeline?limit=1", null);
        assertThat(timeline.status()).isEqualTo(200);
        assertThat(reportJson.readTree(timeline.body()).path("summary").path("visits").asInt()).isEqualTo(2);
        assertThat(reportJson.readTree(timeline.body()).path("visits").get(0).path("sessionId").asText())
            .isEqualTo(session.toString());
        assertThat(call("GET", "/attendance/" + visit + "/timeline?limit=1&offset=1", null).body()).contains("Room B",
            "COLLECTED");
    }
    private String dates() {
        return "from=" + today + "&until=" + today;
    }
    private String path() {
        return "/attendance-reports?" + dates();
    }
    private void setArrival(UUID visit, java.time.Instant time) {
        db.update(CHILDREN_ATTENDANCE_SEGMENTS)
            .set(CHILDREN_ATTENDANCE_SEGMENTS.ARRIVED_AT, time.atOffset(ZoneOffset.UTC))
            .where(CHILDREN_ATTENDANCE_SEGMENTS.ATTENDANCE_ID.eq(visit))
            .execute();
    }
    private void assertVisits(int expected) throws Exception {
        assertThat(reportJson.readTree(call("GET", path(), null).body()).path("summary").path("visits").asInt())
            .isEqualTo(expected);
    }
}
