package org.onechurch.childrenministry.safeguarding.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_AUDIT;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SUPPORT_RECORDS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
class SupportApiIntegrationTest extends ChildrenIntegrationTestSupport {

    @Test
    void careDataIsRestrictedAuditedAndAbsentFromOrdinaryReads() throws Exception {
        ok("PUT", "/support",
            body("childId", child, "expectedVersion", 0, "allergies", "Peanut allergy", "careInstructions",
                "Contact guardian", "restrictedNotes", "Private meeting", "reviewOn", today.plusMonths(1),
                "retainUntil", today.plusYears(1)));
        Response access = call("POST", "/support/access", body("childId", child, "sessionId", session));
        assertThat(access.status()).isEqualTo(200);
        assertThat(access.body()).contains("Peanut allergy");
        assertThat(db.fetchCount(CHILDREN_AUDIT, CHILDREN_AUDIT.ACTION.eq("support.accessed"))).isEqualTo(1);
        assertThat(call("GET", "/enrolments?childId=" + child, null).body()).doesNotContain("Peanut allergy",
            "Private meeting");
        assertThat(request("GET", "/api/people/" + child, admin, null).body()).doesNotContain("Peanut allergy",
            "Private meeting");
        assertThat(call("PUT", "/support", body("childId", child, "expectedVersion", 0, "allergies", "Changed",
            "careInstructions", "", "restrictedNotes", "", "reviewOn", today, "retainUntil", today)).status())
            .isEqualTo(204);
        assertThat(call("PUT", "/support", body("childId", child, "expectedVersion", 0, "allergies", "Changed",
            "careInstructions", "", "restrictedNotes", "", "reviewOn", today, "retainUntil", today)).status())
            .isEqualTo(409);
    }
    @Test
    void expiredSupportCannotBeReadAndPurgeLeavesOnlyAnAuditTrail() throws Exception {
        ok("PUT", "/support", body("childId", child, "expectedVersion", 0, "allergies", "Sensitive", "careInstructions",
            "", "restrictedNotes", "", "reviewOn", today, "retainUntil", today));
        db.update(CHILDREN_SUPPORT_RECORDS)
            .set(CHILDREN_SUPPORT_RECORDS.REVIEW_ON, today.minusDays(2))
            .set(CHILDREN_SUPPORT_RECORDS.RETAIN_UNTIL, today.minusDays(1))
            .where(CHILDREN_SUPPORT_RECORDS.CHILD_ID.eq(child))
            .execute();
        assertThat(call("POST", "/support/access", body("childId", child, "sessionId", session)).status())
            .isEqualTo(404);
        Response result = call("POST", "/support/purge", body("limit", 50));
        assertThat(result.status()).isEqualTo(200);
        assertThat(db.fetchCount(CHILDREN_SUPPORT_RECORDS)).isZero();
        assertThat(db.fetchCount(CHILDREN_AUDIT, CHILDREN_AUDIT.ACTION.eq("support.purged"))).isEqualTo(1);
    }

}
