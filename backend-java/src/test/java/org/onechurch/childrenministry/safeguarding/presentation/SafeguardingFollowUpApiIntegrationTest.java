package org.onechurch.childrenministry.safeguarding.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_AUDIT;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_GUARDIAN_AUTHORITIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SUPPORT_RECORDS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;

import java.time.OffsetDateTime;
import java.time.ZoneOffset;
import java.util.UUID;
class SafeguardingFollowUpApiIntegrationTest extends ChildrenIntegrationTestSupport {
    @Test
    void currentCoverageIsGroupedBeforePagingAndPolicyChangesRequireNewConsent() throws Exception {
        assertThat(call("GET", "/safeguarding-follow-up", null).body()).contains("\"total\":0");
        UUID missing = datedPerson("Missing", today.minusYears(8));
        enrol(missing);
        int audits = db.fetchCount(CHILDREN_AUDIT);
        Response page = call("GET", "/safeguarding-follow-up?search=Missing&limit=1", null);
        assertThat(page.status()).withFailMessage(page.body()).isEqualTo(200);
        assertThat(page.body()).contains("\"total\":3", "\"missing\":3", "\"children\":1", "Missing Mabasa")
            .doesNotContain("evidenceReference");
        assertThat(call("GET", "/safeguarding-follow-up?search=Missing&kind=PICKUP&offset=0", null).body())
            .contains("\"total\":1", "\"missing\":3");
        assertThat(db.fetchCount(CHILDREN_AUDIT)).isEqualTo(audits);
        create("/consent-policies", body("programmeId", programme, "expectedVersion", 1, "label", "Updated policy",
            "documentReference", "consent/next", "contentHash", "b".repeat(64)));
        Response current = call("GET", "/safeguarding-follow-up?search=Child&kind=CONSENT", null);
        assertThat(current.body()).contains("\"state\":\"MISSING\"", "current programme policy", "\"total\":1");
    }
    @Test
    void authorityExpiryLimitsDependentEvidenceAndAlternativeCoveragePreventsFalseAlerts() throws Exception {
        OffsetDateTime expires = OffsetDateTime.now(ZoneOffset.UTC).plusDays(5);
        db.update(CHILDREN_GUARDIAN_AUTHORITIES)
            .set(CHILDREN_GUARDIAN_AUTHORITIES.VALID_UNTIL, expires)
            .where(CHILDREN_GUARDIAN_AUTHORITIES.ID.eq(authority))
            .execute();
        Response expiring = call("GET", "/safeguarding-follow-up?days=7", null);
        assertThat(expiring.body()).contains("\"total\":3", "\"expiring\":3", "GUARDIAN", "CONSENT", "PICKUP");
        UUID alternative = create("/guardian-authorities", body("childId", child, "guardianId", collector, "validFrom",
            from, "validUntil", until, "evidenceReference", "Alternative authority"));
        consent(child, alternative, policy);
        create("/pickup-authorizations",
            body("programmeId", programme, "childId", child, "collectorId", guardian, "authorityId", alternative,
                "validFrom", from, "validUntil", until, "evidenceReference", "Alternative collector"));
        assertThat(call("GET", "/safeguarding-follow-up?days=7", null).body()).contains("\"total\":0");
        db.update(CHILDREN_GUARDIAN_AUTHORITIES)
            .set(CHILDREN_GUARDIAN_AUTHORITIES.REVOKED_AT, OffsetDateTime.now())
            .where(CHILDREN_GUARDIAN_AUTHORITIES.ID.eq(alternative))
            .execute();
        db.update(CHILDREN_GUARDIAN_AUTHORITIES)
            .set(CHILDREN_GUARDIAN_AUTHORITIES.VALID_UNTIL, OffsetDateTime.now().minusSeconds(1))
            .where(CHILDREN_GUARDIAN_AUTHORITIES.ID.eq(authority))
            .execute();
        assertThat(call("GET", "/safeguarding-follow-up", null).body()).contains("\"expired\":3", "\"total\":3");
    }
    @Test
    void careFollowUpExposesOnlyDatesAndOnlyToSupportUsersWithoutReadingThePayload() throws Exception {
        ok("PUT", "/support",
            body("childId", child, "expectedVersion", 0, "allergies", "Peanut secret", "careInstructions",
                "Private care", "restrictedNotes", "Restricted secret", "reviewOn", today, "retainUntil",
                today.plusYears(1)));
        int audits = db.fetchCount(CHILDREN_AUDIT);
        Response page = call("GET", "/safeguarding-follow-up", null);
        assertThat(page.body()).contains("CARE", "\"careDue\":1", "\"careIncluded\":true")
            .doesNotContain("Peanut secret", "Private care", "Restricted secret", "allergies", "restrictedNotes");
        assertThat(db.fetchCount(CHILDREN_AUDIT)).isEqualTo(audits);
        UUID permission = db.select(PERMISSIONS.ID)
            .from(PERMISSIONS)
            .where(PERMISSIONS.CODE.eq("children.support"))
            .fetchOne(PERMISSIONS.ID);
        db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.eq(permission)).execute();
        assertThat(call("GET", "/safeguarding-follow-up", null).body())
            .contains("\"careIncluded\":false", "\"total\":0")
            .doesNotContain("\"kind\":\"CARE\"");
        assertThat(call("GET", "/safeguarding-follow-up?kind=CARE", null).status()).isEqualTo(403);
        assertThat(db.fetchCount(CHILDREN_AUDIT)).isEqualTo(audits);
    }
    @Test
    void expiredCarePayloadsAndInactiveEnrolmentsAreExcludedAndBoundsAreValidated() throws Exception {
        ok("PUT", "/support", body("childId", child, "expectedVersion", 0, "allergies", "Expired secret",
            "careInstructions", "", "restrictedNotes", "", "reviewOn", today, "retainUntil", today));
        db.update(CHILDREN_SUPPORT_RECORDS)
            .set(CHILDREN_SUPPORT_RECORDS.REVIEW_ON, today.minusDays(2))
            .set(CHILDREN_SUPPORT_RECORDS.RETAIN_UNTIL, today.minusDays(1))
            .where(CHILDREN_SUPPORT_RECORDS.CHILD_ID.eq(child))
            .execute();
        assertThat(call("GET", "/safeguarding-follow-up", null).body()).contains("\"total\":0");
        UUID future = datedPerson("Future", today.minusYears(9));
        create("/enrolments", body("programmeId", programme, "childId", future, "validFrom", today.plusDays(1),
            "validUntil", today.plusYears(1)));
        assertThat(call("GET", "/safeguarding-follow-up", null).body()).contains("\"total\":0");
        assertThat(call("GET", "/safeguarding-follow-up?days=91", null).status()).isEqualTo(400);
        assertThat(call("GET", "/safeguarding-follow-up?kind=SECRET", null).status()).isEqualTo(400);
    }
    @Test
    void careReviewIsDeduplicatedAcrossProgrammesAndSafeguardingPermissionIsRequired() throws Exception {
        UUID other = create("/programmes", body("name", "Midweek children", "minStaff", 1, "childrenPerStaff", 2));
        create("/enrolments",
            body("programmeId", other, "childId", child, "validFrom", today, "validUntil", today.plusYears(1)));
        ok("PUT", "/support", body("childId", child, "expectedVersion", 0, "allergies", "Private", "careInstructions",
            "", "restrictedNotes", "", "reviewOn", today, "retainUntil", today.plusYears(1)));
        Response page = call("GET", "/safeguarding-follow-up?kind=CARE", null);
        assertThat(page.status()).isEqualTo(200);
        assertThat(page.body()).contains("\"total\":1", "\"careDue\":1", "All programmes").doesNotContain("Private");
        UUID permission = db.select(PERMISSIONS.ID)
            .from(PERMISSIONS)
            .where(PERMISSIONS.CODE.eq("children.safeguarding"))
            .fetchOne(PERMISSIONS.ID);
        db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.eq(permission)).execute();
        assertThat(call("GET", "/safeguarding-follow-up", null).status()).isEqualTo(403);
        assertThat(request("GET", "/api/children/safeguarding-follow-up", null, null).status()).isEqualTo(401);
    }

}
