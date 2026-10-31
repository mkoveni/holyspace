package org.onechurch.childrenministry.safeguarding.presentation;
import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;

import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.stream.Collectors;
class ChildReadinessApiIntegrationTest extends ChildrenIntegrationTestSupport {
    @Test
    void preparationUsesCurrentEvidenceAndDoesNotRequireOrExposeCareRecords() throws Exception {
        Response response = check(child);
        assertThat(response.status()).isEqualTo(200);
        assertThat(response.data()).containsEntry("ready", true)
            .containsEntry("completed", 7)
            .containsEntry("total", 7)
            .containsEntry("on", today.toString())
            .containsEntry("timeZone", "Africa/Johannesburg");
        assertThat(response.body()).doesNotContain("evidenceReference", "guardianId", "collectorId", "allergies",
            "careInstructions", "restrictedNotes", "pickupCode");
        Response roster = call("GET", "/programmes/" + programme + "/children", null);
        assertThat(roster.body()).contains("\"ready\":true", "\"completed\":7").doesNotContain("steps", "guardianId");
    }
    @Test
    void guidedStepsBecomeCompleteAsEvidenceIsRecorded() throws Exception {
        UUID fresh = datedPerson("New child", today.minusYears(8));
        assertThat(flags(check(fresh))).containsEntry("IDENTITY", true)
            .containsEntry("ENROLMENT", false)
            .containsEntry("CLASS", false)
            .containsEntry("GUARDIAN", false)
            .containsEntry("CONSENT", false)
            .containsEntry("COLLECTOR", false);
        UUID joined = enrol(fresh);
        assign(fresh, joined, group);
        UUID verified = create("/guardian-authorities", body("childId", fresh, "guardianId", guardian, "validFrom",
            from, "validUntil", until, "evidenceReference", "private/guardian"));
        consent(fresh, verified, policy);
        assertThat(flags(check(fresh))).containsEntry("ENROLMENT", true)
            .containsEntry("CLASS", true)
            .containsEntry("GUARDIAN", true)
            .containsEntry("CONSENT", true)
            .containsEntry("COLLECTOR", false);
        create("/pickup-authorizations", body("programmeId", programme, "childId", fresh, "collectorId", collector,
            "authorityId", verified, "validFrom", from, "validUntil", until, "evidenceReference", "private/pickup"));
        assertThat(check(fresh).data()).containsEntry("ready", true);
    }
    @Test
    void policyChangesAndRevokedAuthorityInvalidateReadinessImmediately() throws Exception {
        create("/consent-policies", body("programmeId", programme, "expectedVersion", 1, "label", "Updated policy",
            "documentReference", "consent/new", "contentHash", "b".repeat(64)));
        assertThat(flags(check(child))).containsEntry("CONSENT", false)
            .containsEntry("GUARDIAN", true)
            .containsEntry("COLLECTOR", true);
        ok("POST", "/guardian-authorities/revoke",
            body("id", authority, "expectedVersion", 0, "reason", "Authority withdrawn"));
        assertThat(flags(check(child))).containsEntry("GUARDIAN", false)
            .containsEntry("CONSENT", false)
            .containsEntry("COLLECTOR", false);
    }
    @Test
    void futureEnrolmentsDoNotCountAsCurrentAndMissingPeopleAreNotAssumedEligible() throws Exception {
        UUID future = datedPerson("Future child", today.minusYears(8));
        create("/enrolments", body("programmeId", programme, "childId", future, "validFrom", today.plusDays(1),
            "validUntil", today.plusYears(1)));
        assertThat(flags(check(future))).containsEntry("ENROLMENT", false).containsEntry("CLASS", false);
        assertThat(flags(check(person("Unknown birth date")))).containsEntry("IDENTITY", false);
        assertThat(check(UUID.randomUUID()).status()).isEqualTo(404);
        assertThat(request("GET", "/api/children" + path(child), null, null).status()).isEqualTo(401);
        assertThat(request("GET", "/api/children" + path(child), readerToken(), null).status()).isEqualTo(403);
    }
    private String path(UUID id) {
        return "/programmes/" + programme + "/children/" + id + "/readiness";
    }
    private Response check(UUID id) throws Exception {
        return call("GET", path(id), null);
    }
    @SuppressWarnings("unchecked")
    private Map<String, Boolean> flags(Response response) {
        assertThat(response.status()).isEqualTo(200);
        List<Map<String, Object>> steps = (List<Map<String, Object>>) response.data().get("steps");
        return steps.stream()
            .collect(Collectors.toMap(value -> (String) value.get("code"), value -> (Boolean) value.get("complete")));
    }
}
