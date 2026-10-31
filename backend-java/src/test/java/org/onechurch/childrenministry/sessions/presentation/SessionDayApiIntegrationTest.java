package org.onechurch.childrenministry.sessions.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;

import java.time.Instant;
import java.util.UUID;
class SessionDayApiIntegrationTest extends ChildrenIntegrationTestSupport {
    @Test
    void searchAndPreparationCoverTheSessionAndConsentThroughItsEnd() throws Exception {
        UUID waiting = datedPerson("Waiting", today.minusYears(8));
        UUID joined = enrol(waiting);
        assign(waiting, joined, group);
        Response search = call("GET", path() + "?search=waiting&limit=1", null);
        assertThat(search.status()).isEqualTo(200);
        assertThat(((Number) search.data().get("total")).longValue()).isEqualTo(1);
        assertThat(search.body()).contains("Waiting Mabasa", "\"ready\":false", "through its end")
            .doesNotContain("evidenceReference", "guardianId", "allergies", "pickupCode", "labelHash");
        assertThat(call("GET", path() + "?search=child", null).body()).contains("\"ready\":true");
        assertThat(call("GET", path() + "?limit=1&offset=1", null).status()).isEqualTo(200);
        ok("POST", "/consents/revoke",
            body("id", consent, "expectedVersion", 0, "reason", "Replace with a shorter valid period"));
        create("/consents",
            body("childId", child, "programmeId", programme, "policyId", policy, "authorityId", authority, "validFrom",
                from, "validUntil", end.minusSeconds(300), "acceptedAt", Instant.now(), "evidenceReference",
                "private/short-consent"));
        assertThat(call("GET", path() + "?search=child", null).body()).contains("\"ready\":false", "through its end");
        create("/consent-policies", body("programmeId", programme, "expectedVersion", 1, "label", "New policy",
            "documentReference", "new/policy", "contentHash", "b".repeat(64)));
        assertThat(call("GET", path() + "?search=child", null).body()).contains("\"ready\":false");
    }
    @Test
    void attendanceAndCollectionViewsKeepAccessToPresentChildren() throws Exception {
        arrival();
        ok("POST", "/guardian-authorities/revoke",
            body("id", authority, "expectedVersion", 0, "reason", "Review authority"));
        Response attending = call("GET", path() + "?view=ATTENDING&search=child", null);
        assertThat(attending.status()).isEqualTo(200);
        assertThat(attending.body()).contains("Child Mabasa", "\"attendance\":{", "\"ready\":true")
            .doesNotContain("labelHash", "evidenceReference");
        assertThat(((Number) call("GET", path() + "?view=HISTORY", null).data().get("total")).longValue()).isZero();
    }
    @Test
    void stationOnlyOperatorsAreScopedToTheirPresentAssignment() throws Exception {
        UUID otherRoom = create("/rooms", body("locationId", location, "name", "Other room", "capacity", 10));
        UUID otherClass = create("/classes",
            body("programmeId", programme, "name", "Other class", "minAgeMonths", 0, "maxAgeMonths", 216));
        UUID other = newSession(otherClass, otherRoom, 2);
        UUID permission = db.select(PERMISSIONS.ID)
            .from(PERMISSIONS)
            .where(PERMISSIONS.CODE.eq("children.station"))
            .fetchOne(PERMISSIONS.ID);
        db.deleteFrom(ROLE_PERMISSIONS)
            .where(ROLE_PERMISSIONS.ROLE_ID.eq(ADMIN_ROLE))
            .and(ROLE_PERMISSIONS.PERMISSION_ID.ne(permission))
            .execute();
        assertThat(call("GET", path(), null).status()).isEqualTo(200);
        assertThat(call("GET", "/station/sessions/" + other + "/day", null).status()).isEqualTo(403);
        assertThat(call("GET", path() + "?view=INVALID", null).status()).isEqualTo(400);
        assertThat(call("GET", path() + "?limit=101", null).status()).isEqualTo(400);
        assertThat(request("GET", "/api/children" + path(), null, null).status()).isEqualTo(401);
    }
    private String path() {
        return "/station/sessions/" + session + "/day";
    }
}
