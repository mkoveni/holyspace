package org.onechurch.childrenministry.sessions.presentation;
import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;

import java.util.UUID;
class PickupApiIntegrationTest extends ChildrenIntegrationTestSupport {

    @Test
    void revokedPickupOrGuardianAuthorityPreventsHandoverEvenForSupervisor() throws Exception {
        UUID attendance = attendanceId(arrival());
        Response issued = label(attendance, 0, "");
        ok("POST", "/pickup-authorizations/revoke",
            body("id", grant, "expectedVersion", 0, "reason", "Parent instruction"));
        assertThat(checkout(attendance, 1, (String) issued.data().get("pickupCode"), true, UUID.randomUUID()).status())
            .isEqualTo(409);
        assertThat(call("POST", "/attendance/supervisor-checkout",
            body("attendanceId", attendance, "expectedVersion", 1, "requestId", UUID.randomUUID(), "collectorId",
                collector, "identityVerified", true, "reason", "Lost label"))
            .status()).isEqualTo(409);
        assertThat(call("GET", "/safeguarding/" + child, null).body()).contains("Parent instruction");
    }
    @Test
    void guardianRevocationInvalidatesDerivedConsentAndPickupRights() throws Exception {
        ok("POST", "/guardian-authorities/revoke",
            body("id", authority, "expectedVersion", 0, "reason", "Authority ended"));
        assertThat(arrive(child, session, UUID.randomUUID()).status()).isEqualTo(409);
    }
    @Test
    void relationshipAndHouseholdDoNotGrantPickupRights() throws Exception {
        UUID unrelated = datedPerson("Other adult", today.minusYears(30));
        UUID attendance = attendanceId(arrival());
        Response issued = label(attendance, 0, "");
        Response result = call("POST", "/attendance/checkout",
            body("attendanceId", attendance, "expectedVersion", 1, "requestId", UUID.randomUUID(), "collectorId",
                unrelated, "identityVerified", true, "pickupCode", issued.data().get("pickupCode")));
        assertThat(result.status()).isEqualTo(409);
    }
    @Test
    void collectorMustHaveKnownAdultBirthDate() throws Exception {
        UUID unknown = person("Unknown collector");
        assertThat(call("POST", "/pickup-authorizations",
            body("programmeId", programme, "childId", child, "collectorId", unknown, "authorityId", authority,
                "validFrom", from, "validUntil", until, "evidenceReference", "signed/request"))
            .status()).isEqualTo(400);
    }

}
