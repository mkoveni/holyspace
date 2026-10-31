package org.onechurch.childrenministry.participation.presentation;
import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;

import java.util.UUID;
class ParticipationApiIntegrationTest extends ChildrenIntegrationTestSupport {

    @Test
    void enrolmentIsIndependentOfSessionCapacityAndRequiresKnownChildAge() throws Exception {
        UUID another = datedPerson("Second", today.minusYears(8));
        UUID third = datedPerson("Third", today.minusYears(7));
        assertThat(enrol(another)).isNotNull();
        assertThat(enrol(third)).isNotNull();
        UUID unknown = person("Unknown");
        assertThat(call("POST", "/enrolments",
            body("programmeId", programme, "childId", unknown, "validFrom", today, "validUntil", today.plusYears(1)))
            .status()).isEqualTo(400);
        assertThat(call("POST", "/enrolments",
            body("programmeId", programme, "childId", collector, "validFrom", today, "validUntil", today.plusYears(1)))
            .status()).isEqualTo(400);
        assertThat(call("POST", "/enrolments",
            body("programmeId", programme, "childId", child, "validFrom", today, "validUntil", today.plusMonths(1)))
            .status()).isEqualTo(409);
    }
    @Test
    void classPlacementMustFitEnrolmentAndAgeRange() throws Exception {
        UUID young = create("/classes",
            body("programmeId", programme, "name", "Toddlers", "minAgeMonths", 12, "maxAgeMonths", 36));
        assertThat(call("POST", "/class-assignments",
            body("enrolmentId", enrolment, "classId", young, "validFrom", today, "validUntil", today.plusMonths(1)))
            .status()).isEqualTo(400);
    }
    @Test
    void policyVersionChangesRequireNewConsentAndKeepPreviousEvidence() throws Exception {
        UUID next = create("/consent-policies", body("programmeId", programme, "expectedVersion", 1, "label", "2027",
            "documentReference", "consent/2027", "contentHash", "b".repeat(64)));
        assertThat(arrive(child, session, UUID.randomUUID()).status()).isEqualTo(409);
        consent(child, authority, next);
        assertThat(arrival().status()).isEqualTo(200);
        assertThat(call("GET", "/consent-policies/" + policy, null).status()).isEqualTo(200);
    }
    @Test
    void endedEnrolmentBlocksAdmissionAndStaleEditsFail() throws Exception {
        ok("POST", "/enrolments/end", body("enrolmentId", enrolment, "expectedVersion", 0));
        assertThat(call("POST", "/enrolments/end", body("enrolmentId", enrolment, "expectedVersion", 0)).status())
            .isEqualTo(409);
        assertThat(arrive(child, session, UUID.randomUUID()).status()).isEqualTo(409);
    }
    @Test
    void readPermissionDoesNotExposeSafeguardingOrSupport() throws Exception {
        String reader = readerToken();
        assertThat(request("GET", "/api/children/programmes", reader, null).status()).isEqualTo(403);
        assertThat(request("GET", "/api/children/safeguarding/" + child, reader, null).status()).isEqualTo(403);
        assertThat(request("POST", "/api/children/support/access", reader, body("childId", child, "sessionId", session))
            .status()).isEqualTo(403);
        assertThat(request("GET", "/api/children/programmes", null, null).status()).isEqualTo(401);
    }

    @Test
    void endingEnrolmentEndsItsPlacementsAndAllowsCleanReenrolment() throws Exception {
        ok("POST", "/enrolments/end", body("enrolmentId", enrolment, "expectedVersion", 0));
        UUID fresh = enrol(child);
        assign(child, fresh, group);
        assertThat(arrival().status()).isEqualTo(200);
        assertThat(call("GET", "/class-assignments?childId=" + child, null).body()).contains("endedAt");
    }
}
