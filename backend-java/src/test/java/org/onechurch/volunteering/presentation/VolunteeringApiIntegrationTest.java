package org.onechurch.volunteering.presentation;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.VolunteerIntegrationTestSupport;
import org.onechurch.shared.infrastructure.jooq.Tables;

import java.time.OffsetDateTime;
import java.util.List;
import java.util.Map;
import java.util.UUID;

class VolunteeringApiIntegrationTest extends VolunteerIntegrationTestSupport {
    @Test
    void confirmationAndReadinessAreIndependentAndCredentialRevocationRemainsLive() throws Exception {
        UUID assignment = book(volunteer, "CONFIRMED");
        Response before = serving("GET", "/shifts/" + shift, null);
        assertThat(before.data().get("confirmed")).isEqualTo(1);
        assertThat(before.data().get("ready")).isEqualTo(0);
        ready();
        assertThat(serving("GET", "/shifts/" + shift, null).data().get("ready")).isEqualTo(1);
        Map<?, ?> credential =
            (Map<?, ?>) ((List<?>) serving("GET", "/volunteers/" + volunteer, null).data().get("credentials"))
                .getFirst();
        assertThat(serving("POST", "/volunteers/" + volunteer + "/credentials/revoke", body("expectedVersion",
            volunteerVersion(), "credentialId", credential.get("id"), "reason", "Evidence withdrawn")).status())
            .isEqualTo(204);
        Response after = serving("GET", "/shifts/" + shift, null);
        assertThat(after.data().get("confirmed")).isEqualTo(1);
        assertThat(after.data().get("ready")).isEqualTo(0);
        assertThat(after.body()).doesNotContain("Verified source");
        assertThat(assignment).isNotNull();
    }
    @Test
    void matchingRequestRetriesAreIdempotentAndChangedRequestsAndStaleVersionsAreRejected() throws Exception {
        UUID key = UUID.randomUUID();
        Map<String, Object> payload = draft(key, start.plusSeconds(7200), end.plusSeconds(7200), 1);
        UUID first = add("/shifts", payload);
        assertThat(add("/shifts", payload)).isEqualTo(first);
        payload.put("positions", 2);
        assertThat(serving("POST", "/shifts", payload).status()).isEqualTo(409);
        book(volunteer, "PROPOSED");
        assertThat(serving("PUT", "/shifts/" + shift,
            body("expectedVersion", 0, "startsAt", start, "endsAt", end, "positions", 2, "status", "OPEN")).status())
            .isEqualTo(409);
    }
    @Test
    void overlappingAssignmentsAreRejectedButAdjacentDutiesAreAllowed() throws Exception {
        book(volunteer, "PROPOSED");
        UUID other = add("/shifts", draft(UUID.randomUUID(), start.plusSeconds(60), end.plusSeconds(60), 1));
        assertThat(serving("POST", "/assignments", body("requestId", UUID.randomUUID(), "shiftId", other,
            "expectedVersion", 0, "volunteerId", volunteer, "status", "CONFIRMED")).status()).isEqualTo(409);
        UUID adjacent = add("/shifts", draft(UUID.randomUUID(), end, end.plusSeconds(3600), 1));
        assertThat(serving("POST", "/assignments", body("requestId", UUID.randomUUID(), "shiftId", adjacent,
            "expectedVersion", 0, "volunteerId", volunteer, "status", "PROPOSED")).status()).isEqualTo(201);
    }
    @Test
    void anotherBookingDoesNotInvalidateAnInvitationButAChangedDutyDoes() throws Exception {
        UUID a = book(volunteer, "PROPOSED");
        UUID invite = add("/assignments/" + a + "/invitations", body("expectedVersion", 0, "expiresAt", start));
        UUID another = add("/volunteers", body("personId", person("Naledi")));
        book(another, "PROPOSED");
        assertThat(
            serving("POST", "/invitations/" + invite + "/respond", body("expectedVersion", 0, "decision", "ACCEPTED"))
                .status())
            .isEqualTo(204);
        UUID fresh = add("/assignments/" + a + "/invitations", body("expectedVersion", 1, "expiresAt", start));
        assertThat(serving("PUT", "/shifts/" + shift, body("expectedVersion", shiftVersion(shift), "startsAt",
            start.plusSeconds(60), "endsAt", end.plusSeconds(60), "positions", 2, "status", "OPEN")).status())
            .isEqualTo(204);
        assertThat(
            serving("POST", "/invitations/" + fresh + "/respond", body("expectedVersion", 0, "decision", "ACCEPTED"))
                .status())
            .isEqualTo(409);
    }
    @Test
    void invitationsBelongToTheLinkedAccountAndDecliningReleasesCapacity() throws Exception {
        UUID a = book(volunteer, "PROPOSED");
        UUID invite = add("/assignments/" + a + "/invitations", body("expectedVersion", 0, "expiresAt", start));
        String reader = readerToken();
        assertThat(request("POST", "/api/volunteering/invitations/" + invite + "/respond", reader,
            body("expectedVersion", 0, "decision", "ACCEPTED")).status()).isEqualTo(403);
        assertThat(
            serving("POST", "/invitations/" + invite + "/respond", body("expectedVersion", 0, "decision", "DECLINED"))
                .status())
            .isEqualTo(204);
        assertThat(serving("GET", "/shifts/" + shift, null).data().get("reserved")).isEqualTo(0);
        assertThat(
            serving("POST", "/invitations/" + invite + "/respond", body("expectedVersion", 0, "decision", "ACCEPTED"))
                .status())
            .isEqualTo(409);
    }
    @Test
    void atomicBulkRollsBackEveryShiftWhenOneDraftFails() throws Exception {
        UUID good = UUID.randomUUID();
        Map<String, Object> bad = draft(UUID.randomUUID(), end, start, 1);
        assertThat(serving("POST", "/shifts/bulk", List.of(draft(good, end, end.plusSeconds(60), 1), bad)).status())
            .isEqualTo(400);
        assertThat(
            serving("GET", "/shifts?from=" + start + "&until=" + end.plusSeconds(86400), null).data().get("total"))
            .isEqualTo(1);
    }
    @Test
    void fullDutyCredentialCoverageIsRequiredForReadinessButNeverBlocksPlanning() throws Exception {
        add("/volunteers/" + volunteer + "/credentials", body("expectedVersion", 0, "typeId", type, "validFrom",
            validFrom, "validUntil", start.plusSeconds(30), "evidence", "Ends during duty"));
        book(volunteer, "CONFIRMED");
        Response result = serving("GET", "/shifts/" + shift, null);
        assertThat(result.data().get("ready")).isEqualTo(0);
        assertThat(result.body()).contains("Screening must be verified");
    }
    @Test
    void protectedEndpointsRejectAnonymousAndReadOnlyCoordinators() throws Exception {
        assertThat(request("GET", "/api/volunteering/teams", null, null).status()).isEqualTo(401);
        assertThat(request("POST", "/api/volunteering/teams", readerToken(), body("name", "Forbidden")).status())
            .isEqualTo(403);
    }

    @Test
    void expiringAnInvitationReleasesOnlyProposedHoldsAndQueriesNeverMaintainState() throws Exception {
        UUID assignment = book(volunteer, "CONFIRMED");
        UUID invitation =
            add("/assignments/" + assignment + "/invitations", body("expectedVersion", 0, "expiresAt", start));
        db.update(Tables.SERVING_INVITATIONS)
            .set(Tables.SERVING_INVITATIONS.EXPIRES_AT, OffsetDateTime.now().minusSeconds(1))
            .where(Tables.SERVING_INVITATIONS.ID.eq(invitation))
            .execute();
        assertThat(serving("GET", "/me/invitations", null).body()).contains("EXPIRED");
        assertThat(db.select(Tables.SERVING_INVITATIONS.STATUS)
            .from(Tables.SERVING_INVITATIONS)
            .where(Tables.SERVING_INVITATIONS.ID.eq(invitation))
            .fetchSingle()
            .value1()).isEqualTo("PENDING");
        assertThat(serving("POST", "/invitations/expire", body("limit", 100)).status()).isEqualTo(200);
        assertThat(serving("GET", "/shifts/" + shift, null).data().get("confirmed")).isEqualTo(1);
    }
    @Test
    void revisedDutyTimesRequireRenewedCommitment() throws Exception {
        book(volunteer, "CONFIRMED");
        assertThat(serving("PUT", "/shifts/" + shift, body("expectedVersion", shiftVersion(shift), "startsAt",
            start.plusSeconds(60), "endsAt", end.plusSeconds(60), "positions", 2, "status", "OPEN")).status())
            .isEqualTo(204);
        Response revised = serving("GET", "/shifts/" + shift, null);
        assertThat(revised.data().get("confirmed")).isEqualTo(0);
        assertThat(revised.body()).contains("PROPOSED");
    }
}
