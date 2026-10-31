package org.onechurch.volunteering.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_INVITATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_VOLUNTEERS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.VolunteerIntegrationTestSupport;

import java.time.OffsetDateTime;
import java.util.List;
import java.util.Map;
import java.util.UUID;
class MemberVolunteeringApiIntegrationTest extends VolunteerIntegrationTestSupport {
    private static final String ROOT = "/api/member/volunteering";
    @Test
    void sourceCommandsCreatePrivateDeduplicatedInboxNoticesAndHonorOptOut() throws Exception {
        UUID key = UUID.randomUUID();
        Map<String, Object> draft = body("requestId", key, "shiftId", shift, "expectedVersion", 0, "volunteerId",
            volunteer, "status", "PROPOSED");
        UUID assignment = add("/assignments", draft);
        assertThat(add("/assignments", draft)).isEqualTo(assignment);
        Response assigned = request("GET", "/api/member/notifications", admin, null);
        assertThat(assigned.data()).containsEntry("unreadCount", 1);
        assertThat(assigned.body()).contains("A duty has been added").doesNotContain("Solly", "accountId", "sourceKey");
        add("/assignments/" + assignment + "/invitations", body("expectedVersion", 0, "expiresAt", start));
        assertThat(request("GET", "/api/member/notifications", admin, null).body()).contains("invited to serve");
        assertThat(serving("PUT", "/shifts/" + shift, body("expectedVersion", shiftVersion(shift), "startsAt", start,
            "endsAt", end, "positions", 2, "status", "CANCELLED")).status()).isEqualTo(204);
        Response changed = request("GET", "/api/member/notifications", admin, null);
        assertThat(changed.data()).containsEntry("unreadCount", 3);
        assertThat(changed.body()).contains("serving plans have changed");
        assertThat(request("GET", "/api/member/notifications", readerToken(), null).data()).containsEntry("unreadCount",
            0);
        assertThat(request("PUT", "/api/member/notification-preferences/VOLUNTEERING", admin,
            body("enabled", false, "expectedVersion", 0)).status()).isEqualTo(204);
        UUID next = add("/shifts", draft(UUID.randomUUID(), end, end.plusSeconds(3600), 1));
        add("/assignments", body("requestId", UUID.randomUUID(), "shiftId", next, "expectedVersion", 0, "volunteerId",
            volunteer, "status", "PROPOSED"));
        assertThat(request("GET", "/api/member/notifications", admin, null).data()).containsEntry("unreadCount", 3);
    }
    @Test
    void ownOverviewIncludesAssignmentsWithoutInvitationsAndExcludesPrivateEvidence() throws Exception {
        ready();
        UUID assignment = book(volunteer, "CONFIRMED");
        UUID other = add("/volunteers", body("personId", person("Other")));
        UUID hidden = book(other, "PROPOSED");
        Response response = request("GET", ROOT, admin, null);
        assertThat(response.status()).isEqualTo(200);
        assertThat(response.data().get("linked")).isEqualTo(true);
        assertThat(response.body()).contains(assignment.toString(), "Welcome team", "Welcomer", "\"ready\":true")
            .doesNotContain(hidden.toString(), "accountId", "personId", "Verified source", "requestHash", "createdBy");
        assertThat(request("GET", ROOT + "?limit=0", admin, null).status()).isEqualTo(400);
        assertThat(request("GET", ROOT, null, null).status()).isEqualTo(401);
    }
    @Test
    void scopeNeedsBothLiveServingPermissionAndExplicitVolunteerLink() throws Exception {
        UUID assignment = book(volunteer, "PROPOSED");
        assertThat(request("GET", ROOT, readerToken(), null).body()).contains("\"permitted\":false")
            .doesNotContain(assignment.toString());
        db.update(SERVING_VOLUNTEERS)
            .set(SERVING_VOLUNTEERS.ACCOUNT_ID, (UUID) null)
            .where(SERVING_VOLUNTEERS.ID.eq(volunteer))
            .execute();
        assertThat(request("GET", ROOT, admin, null).body()).contains("\"linked\":false")
            .doesNotContain(assignment.toString());
        assertThat(request("PUT", ROOT + "/availability", admin,
            body("volunteerId", volunteer, "expectedVersion", 0, "weekly", List.of(), "exceptions", List.of()))
            .status()).isEqualTo(404);
    }
    @Test
    void responseUsesOwnInvitationVersionsAndDoesNotClaimReadiness() throws Exception {
        UUID assignment = book(volunteer, "PROPOSED");
        UUID invitation =
            add("/assignments/" + assignment + "/invitations", body("expectedVersion", 0, "expiresAt", start));
        assertThat(request("POST", ROOT + "/invitations/" + invitation + "/respond", admin,
            body("expectedVersion", 0, "decision", "ACCEPTED")).status()).isEqualTo(204);
        assertThat(request("GET", ROOT, admin, null).body()).contains("CONFIRMED", "\"ready\":false");
        assertThat(request("POST", ROOT + "/invitations/" + invitation + "/respond", admin,
            body("expectedVersion", 0, "decision", "DECLINED")).status()).isEqualTo(409);
        UUID other = add("/volunteers", body("personId", person("Other")));
        UUID otherAssignment = book(other, "PROPOSED");
        UUID otherInvitation =
            add("/assignments/" + otherAssignment + "/invitations", body("expectedVersion", 0, "expiresAt", start));
        assertThat(request("POST", ROOT + "/invitations/" + otherInvitation + "/respond", admin,
            body("expectedVersion", 0, "decision", "ACCEPTED")).status()).isEqualTo(404);
    }
    @Test
    void expiryIsReadOnlyAndChangedDutyCannotBeAccepted() throws Exception {
        UUID assignment = book(volunteer, "PROPOSED");
        UUID invitation =
            add("/assignments/" + assignment + "/invitations", body("expectedVersion", 0, "expiresAt", start));
        db.update(SERVING_INVITATIONS)
            .set(SERVING_INVITATIONS.EXPIRES_AT, OffsetDateTime.now().minusMinutes(1))
            .where(SERVING_INVITATIONS.ID.eq(invitation))
            .execute();
        assertThat(request("GET", ROOT, admin, null).body()).contains("EXPIRED");
        assertThat(db.select(SERVING_INVITATIONS.STATUS)
            .from(SERVING_INVITATIONS)
            .where(SERVING_INVITATIONS.ID.eq(invitation))
            .fetchOne(SERVING_INVITATIONS.STATUS)).isEqualTo("PENDING");
        assertThat(request("POST", ROOT + "/invitations/" + invitation + "/respond", admin,
            body("expectedVersion", 0, "decision", "ACCEPTED")).status()).isEqualTo(409);
    }
    @Test
    void availabilityIsVersionedAndNeverCancelsAnExistingDuty() throws Exception {
        ready();
        UUID assignment = book(volunteer, "CONFIRMED");
        long version = volunteerVersion();
        Map<String, Object> payload = body("volunteerId", volunteer, "expectedVersion", version, "weekly", List.of(),
            "exceptions", List.of(body("startsAt", start, "endsAt", end, "available", false)));
        assertThat(request("PUT", ROOT + "/availability", admin, payload).status()).isEqualTo(204);
        assertThat(request("GET", ROOT, admin, null).body()).contains(assignment.toString(), "CONFIRMED",
            "\"ready\":false", "\"available\":false");
        assertThat(request("PUT", ROOT + "/availability", admin, payload).status()).isEqualTo(409);
        assertThat(
            request("PUT", ROOT + "/availability", admin, body("volunteerId", volunteer, "expectedVersion", version + 1,
                "weekly", List.of(), "exceptions", List.of(body("startsAt", end, "endsAt", start, "available", false))))
                .status())
            .isEqualTo(400);
        db.update(SERVING_VOLUNTEERS)
            .set(SERVING_VOLUNTEERS.STATUS, "PAUSED")
            .where(SERVING_VOLUNTEERS.ID.eq(volunteer))
            .execute();
        assertThat(request("PUT", ROOT + "/availability", admin, payload).status()).isEqualTo(403);
    }
    @Test
    void boundedPagingAndRevokedServingPermissionAreEnforced() throws Exception {
        book(volunteer, "PROPOSED");
        UUID nextShift = add("/shifts", draft(UUID.randomUUID(), end, end.plusSeconds(3600), 1));
        add("/assignments", body("requestId", UUID.randomUUID(), "shiftId", nextShift, "expectedVersion", 0,
            "volunteerId", volunteer, "status", "CONFIRMED"));
        assertThat(request("GET", ROOT + "?limit=1", admin, null).data().get("hasMore")).isEqualTo(true);
        assertThat(request("GET", ROOT + "?limit=1&offset=1", admin, null).data().get("hasMore")).isEqualTo(false);
        db.deleteFrom(ROLE_PERMISSIONS)
            .where(ROLE_PERMISSIONS.PERMISSION_ID.in(db.select(PERMISSIONS.ID)
                .from(PERMISSIONS)
                .where(PERMISSIONS.CODE.in("volunteering.serve", "volunteering.manage"))))
            .execute();
        assertThat(request("GET", ROOT, admin, null).body()).contains("\"permitted\":false").doesNotContain("Welcomer");
        assertThat(request("PUT", ROOT + "/availability", admin,
            body("volunteerId", volunteer, "expectedVersion", 0, "weekly", List.of(), "exceptions", List.of()))
            .status()).isEqualTo(403);
    }

    @Test
    void ownServingDoesNotNeedAnyStaffPermission() throws Exception {
        UUID assignment = book(volunteer, "PROPOSED");
        UUID invitation =
            add("/assignments/" + assignment + "/invitations", body("expectedVersion", 0, "expiresAt", start));
        UUID serve = db.select(PERMISSIONS.ID)
            .from(PERMISSIONS)
            .where(PERMISSIONS.CODE.eq("volunteering.serve"))
            .fetchOne(PERMISSIONS.ID);
        db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.ne(serve)).execute();
        assertThat(request("GET", ROOT, admin, null).status()).isEqualTo(200);
        assertThat(request("GET", "/api/volunteering/volunteers", admin, null).status()).isEqualTo(403);
        assertThat(request("POST", ROOT + "/invitations/" + invitation + "/respond", admin,
            body("expectedVersion", 0, "decision", "DECLINED")).status()).isEqualTo(204);
        assertThat(request("PUT", ROOT + "/availability", admin,
            body("volunteerId", volunteer, "expectedVersion", 0, "weekly", List.of(), "exceptions", List.of()))
            .status()).isEqualTo(204);
    }
    @Test
    void anOldDraftCannotApplyToADifferentLinkedVolunteerWithTheSameVersion() throws Exception {
        UUID other = add("/volunteers", body("personId", person("Other")));
        db.update(SERVING_VOLUNTEERS)
            .set(SERVING_VOLUNTEERS.ACCOUNT_ID, (UUID) null)
            .where(SERVING_VOLUNTEERS.ID.eq(volunteer))
            .execute();
        db.update(SERVING_VOLUNTEERS)
            .set(SERVING_VOLUNTEERS.ACCOUNT_ID, adminId)
            .where(SERVING_VOLUNTEERS.ID.eq(other))
            .execute();
        assertThat(request("PUT", ROOT + "/availability", admin,
            body("volunteerId", volunteer, "expectedVersion", 0, "weekly", List.of(), "exceptions", List.of()))
            .status()).isEqualTo(409);
        assertThat(db.select(SERVING_VOLUNTEERS.VERSION)
            .from(SERVING_VOLUNTEERS)
            .where(SERVING_VOLUNTEERS.ID.eq(other))
            .fetchOne(SERVING_VOLUNTEERS.VERSION)).isZero();
    }
}
