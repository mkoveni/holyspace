package org.onechurch.childrenministry.participation.presentation;
import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;

import java.util.UUID;
class ProgrammeRosterApiIntegrationTest extends ChildrenIntegrationTestSupport {
    @Test
    void programmeRosterIncludesClassPlacementsAndSearchesBeforePagination() throws Exception {
        UUID other = datedPerson("Zanele", today.minusYears(8));
        enrol(other);
        Response response = call("GET", "/programmes/" + programme + "/children?search=child&limit=1", null);
        assertThat(response.status()).isEqualTo(200);
        assertThat(((Number) response.data().get("total")).longValue()).isEqualTo(1);
        assertThat(response.body()).contains("Child Mabasa", "Juniors", "ACTIVE", child.toString())
            .doesNotContain("Zanele", "evidenceReference", "allergies", "guardianId", "pickupCode");
        assertThat(call("GET", "/programmes/" + programme + "/children?limit=1&offset=1", null).data().get("children"))
            .asList()
            .hasSize(1);
        assertThat(call("GET", "/programmes/" + programme + "/children?search=missing", null).data().get("children"))
            .asList()
            .isEmpty();
        assertThat(call("GET", "/programmes/" + UUID.randomUUID() + "/children", null).status()).isEqualTo(404);
    }
    @Test
    void statusesRespectExclusiveEndDatesAndProgrammeScope() throws Exception {
        UUID future = datedPerson("Future", today.minusYears(8));
        create("/enrolments", body("programmeId", programme, "childId", future, "validFrom", today.plusDays(1),
            "validUntil", today.plusMonths(2)));
        UUID expired = datedPerson("Expired", today.minusYears(8));
        create("/enrolments",
            body("programmeId", programme, "childId", expired, "validFrom", today.minusMonths(1), "validUntil", today));
        UUID anotherProgramme =
            create("/programmes", body("name", "Other ministry", "minStaff", 1, "childrenPerStaff", 5));
        create("/enrolments", body("programmeId", anotherProgramme, "childId", child, "validFrom", today, "validUntil",
            today.plusMonths(1)));
        assertThat(call("GET", path("ACTIVE"), null).body()).contains(child.toString())
            .doesNotContain(future.toString(), expired.toString());
        assertThat(call("GET", path("UPCOMING"), null).body()).contains(future.toString())
            .doesNotContain(child.toString());
        assertThat(call("GET", path("EXPIRED"), null).body()).contains(expired.toString())
            .doesNotContain(child.toString());
        ok("POST", "/enrolments/end", body("enrolmentId", enrolment, "expectedVersion", 0));
        assertThat(call("GET", path("ENDED"), null).body()).contains(child.toString()).doesNotContain("Juniors");
        assertThat(((Number) call("GET", path("ALL"), null).data().get("total")).longValue()).isEqualTo(3);
    }
    @Test
    void rosterRequiresPermissionAndValidFilters() throws Exception {
        String path = "/api/children/programmes/" + programme + "/children";
        assertThat(request("GET", path, null, null).status()).isEqualTo(401);
        assertThat(request("GET", path, readerToken(), null).status()).isEqualTo(403);
        assertThat(call("GET", "/programmes/" + programme + "/children?status=UNKNOWN", null).status()).isEqualTo(400);
        assertThat(call("GET", "/programmes/" + programme + "/children?limit=101", null).status()).isEqualTo(400);
    }
    private String path(String status) {
        return "/programmes/" + programme + "/children?status=" + status;
    }
}
