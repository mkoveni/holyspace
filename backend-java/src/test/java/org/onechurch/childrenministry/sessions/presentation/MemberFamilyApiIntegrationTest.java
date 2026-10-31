package org.onechurch.childrenministry.sessions.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ATTENDANCE;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_CLASS_ASSIGNMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_CONSENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_GUARDIAN_AUTHORITIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_PICKUP_AUTHORIZATIONS;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;

import java.time.Instant;
import java.time.OffsetDateTime;
import java.util.Map;
import java.util.UUID;
class MemberFamilyApiIntegrationTest extends ChildrenIntegrationTestSupport {
    private static final String ROOT = "/api/member/children";
    private UUID account;
    private String member;
    @BeforeEach
    void member() throws Exception {
        account = createUser("familymember", "family@example.org", "Family member");
        member = login("familymember", PASSWORD);
    }
    private void link() throws Exception {
        assertThat(request("PUT", "/api/member-access/" + account, admin,
            body("personId", guardian, "active", true, "expectedVersion", 0, "evidence", "Verified in person"))
            .status()).isEqualTo(204);
    }
    private String path(String suffix) {
        return ROOT + "/" + child + suffix;
    }
    private UUID selfGrant() throws Exception {
        return create("/pickup-authorizations",
            body("programmeId", programme, "childId", child, "collectorId", guardian, "authorityId", authority,
                "validFrom", from, "validUntil", until, "evidenceReference", "Signed guardian pickup"));
    }
    private Response myLabel(UUID attendance, long version) throws Exception {
        return request("POST", path("/attendance/" + attendance + "/pickup-label"), member,
            body("expectedVersion", version, "replacePrevious", true));
    }
    @Test
    void onlyExplicitCurrentGuardianAuthorityGrantsOwnReadsWithoutStaffPermissions() throws Exception {
        assertThat(request("GET", ROOT, null, null).status()).isEqualTo(401);
        assertThat(request("GET", ROOT, member, null).body()).contains("\"verified\":false");
        link();
        Response list = request("GET", ROOT, member, null);
        assertThat(list.status()).isEqualTo(200);
        assertThat(list.body()).contains(child.toString(), "Child Mabasa")
            .doesNotContain("evidenceReference", "verifiedBy", "guardianId", "contacts");
        assertThat(request("GET", path(""), member, null).status()).isEqualTo(200);
        assertThat(request("GET", "/api/children/programmes", member, null).status()).isEqualTo(403);
        assertThat(request("GET", ROOT + "/" + UUID.randomUUID(), member, null).status()).isEqualTo(404);
        assertThat(request("GET", ROOT + "?limit=0", member, null).status()).isEqualTo(400);
    }
    @Test
    void peopleParentRelationshipDoesNotGrantMinistryAccess() throws Exception {
        link();
        UUID other = datedPerson("Other child", today.minusYears(8));
        assertThat(request("POST", "/api/relationships", admin,
            body("sourceId", guardian, "targetId", other, "type", "PARENT_OF")).status()).isEqualTo(201);
        assertThat(request("GET", ROOT + "/" + other, member, null).status()).isEqualTo(404);
        assertThat(request("GET", ROOT, member, null).body()).doesNotContain(other.toString());
        assertThat(request("GET", ROOT + "/" + other + "/attendance", member, null).status()).isEqualTo(404);
    }
    @Test
    void expiryRevocationAndFutureAuthorityRemoveAccess() throws Exception {
        link();
        db.update(CHILDREN_GUARDIAN_AUTHORITIES)
            .set(CHILDREN_GUARDIAN_AUTHORITIES.VALID_FROM, OffsetDateTime.now().plusMinutes(1))
            .where(CHILDREN_GUARDIAN_AUTHORITIES.ID.eq(authority))
            .execute();
        assertThat(request("GET", ROOT, member, null).body()).doesNotContain(child.toString());
        db.update(CHILDREN_GUARDIAN_AUTHORITIES)
            .set(CHILDREN_GUARDIAN_AUTHORITIES.VALID_FROM, OffsetDateTime.now().minusDays(2))
            .set(CHILDREN_GUARDIAN_AUTHORITIES.VALID_UNTIL, OffsetDateTime.now().minusSeconds(1))
            .where(CHILDREN_GUARDIAN_AUTHORITIES.ID.eq(authority))
            .execute();
        assertThat(request("GET", path("/sessions"), member, null).status()).isEqualTo(404);
        db.update(CHILDREN_GUARDIAN_AUTHORITIES)
            .set(CHILDREN_GUARDIAN_AUTHORITIES.VALID_UNTIL, OffsetDateTime.now().plusDays(1))
            .set(CHILDREN_GUARDIAN_AUTHORITIES.REVOKED_AT, OffsetDateTime.now())
            .where(CHILDREN_GUARDIAN_AUTHORITIES.ID.eq(authority))
            .execute();
        assertThat(request("GET", path("/programmes"), member, null).status()).isEqualTo(404);
    }
    @Test
    void programmeReadinessAndSessionPreparationAreMinimalReadOnlySnapshots() throws Exception {
        link();
        Response programmes = request("GET", path("/programmes"), member, null);
        assertThat(programmes.status()).isEqualTo(200);
        assertThat(programmes.body()).contains("Sunday children", "Juniors", "CONSENT")
            .doesNotContain("evidenceReference", "signed/consent", "authorityId");
        Response dates = request("GET", path("/sessions"), member, null);
        assertThat(dates.status()).isEqualTo(200);
        assertThat(dates.body()).contains(session.toString(), "Africa/Johannesburg", "Room A", "Main campus")
            .doesNotContain("Worker Mabasa", "screenedUntil");
        db.update(CHILDREN_CONSENTS)
            .set(CHILDREN_CONSENTS.REVOKED_AT, OffsetDateTime.now())
            .where(CHILDREN_CONSENTS.ID.eq(consent))
            .execute();
        assertThat(request("GET", path("/sessions"), member, null).body()).contains("Guardian consent");
        assertThat(db.fetchCount(CHILDREN_ATTENDANCE)).isZero();
        db.update(CHILDREN_CLASS_ASSIGNMENTS)
            .set(CHILDREN_CLASS_ASSIGNMENTS.VALID_UNTIL, today)
            .where(CHILDREN_CLASS_ASSIGNMENTS.ID.eq(assignment))
            .execute();
        assertThat(request("GET", path("/sessions"), member, null).body()).doesNotContain(session.toString());
    }
    @Test
    void attendanceOmitsCollectorIdentitySupportNotesAndAuditEvidence() throws Exception {
        link();
        UUID visit = attendanceId(arrival());
        Response history = request("GET", path("/attendance"), member, null);
        assertThat(history.status()).isEqualTo(200);
        assertThat(history.body()).contains(visit.toString(), "Sunday children", "\"canRequestPickupLabel\":false")
            .doesNotContain("collectorId", "authorizationId", "arrivedBy", "labelHash", "pickupCode", "evidence",
                "exceptionReason");
        db.update(CHILDREN_GUARDIAN_AUTHORITIES)
            .set(CHILDREN_GUARDIAN_AUTHORITIES.VALID_FROM, OffsetDateTime.now().plusSeconds(1))
            .where(CHILDREN_GUARDIAN_AUTHORITIES.ID.eq(authority))
            .execute();
        assertThat(request("GET", path("/attendance"), member, null).status()).isEqualTo(404);
    }
    @Test
    void memberCollectionQrRequiresOwnGrantReplacesOldLabelAndRetainsIdentityChecks() throws Exception {
        link();
        UUID visit = attendanceId(arrival());
        Response old = label(visit, 0, "");
        assertThat(myLabel(visit, 1).status()).isEqualTo(409);
        selfGrant();
        Response issued = myLabel(visit, 1);
        assertThat(issued.status()).isEqualTo(200);
        Instant expires = Instant.parse((String) issued.data().get("expiresAt"));
        assertThat(expires).isBefore(Instant.now().plusSeconds(301));
        String code = (String) issued.data().get("pickupCode");
        assertThat(myLabel(visit, 1).status()).isEqualTo(409);
        assertThat(request("POST", path("/attendance/" + visit + "/pickup-label"), member,
            body("expectedVersion", 2, "replacePrevious", false)).status()).isEqualTo(400);
        assertThat(checkout(visit, 2, (String) old.data().get("pickupCode"), true, UUID.randomUUID()).status())
            .isEqualTo(400);
        long version = db.select(CHILDREN_ATTENDANCE.VERSION)
            .from(CHILDREN_ATTENDANCE)
            .where(CHILDREN_ATTENDANCE.ID.eq(visit))
            .fetchOne(CHILDREN_ATTENDANCE.VERSION);
        Map<String, Object> body = body("attendanceId", visit, "expectedVersion", version, "requestId",
            UUID.randomUUID(), "collectorId", guardian, "identityVerified", false, "pickupCode", code);
        assertThat(call("POST", "/attendance/checkout", body).status()).isEqualTo(400);
        body.put("identityVerified", true);
        assertThat(call("POST", "/attendance/checkout", body).status()).isEqualTo(200);
        assertThat(myLabel(visit, version + 1).status()).isEqualTo(409);
    }
    @Test
    void revokedPickupRightsAndUnknownAttendanceCannotIssueCollectionLabels() throws Exception {
        link();
        UUID own = selfGrant();
        UUID visit = attendanceId(arrival());
        db.update(CHILDREN_PICKUP_AUTHORIZATIONS)
            .set(CHILDREN_PICKUP_AUTHORIZATIONS.REVOKED_AT, OffsetDateTime.now())
            .where(CHILDREN_PICKUP_AUTHORIZATIONS.ID.eq(own))
            .execute();
        assertThat(myLabel(visit, 0).status()).isEqualTo(409);
        assertThat(myLabel(UUID.randomUUID(), 0).status()).isEqualTo(404);
        assertThat(request("GET", path("/attendance"), member, null).body())
            .contains("\"canRequestPickupLabel\":false");
    }

    @Test
    void sessionEligibilityUsesItsLocationCalendarDateAtMidnight() throws Exception {
        link();
        java.time.ZonedDateTime local =
            today.plusDays(1).atTime(0, 15).atZone(java.time.ZoneId.of("Africa/Johannesburg"));
        UUID late = create("/sessions", body("classId", group, "roomId", room, "startsAt", local.toInstant(), "endsAt",
            local.plusMinutes(30).toInstant(), "seatLimit", 2));
        assertThat(request("GET", path("/sessions"), member, null).body()).contains(late.toString());
        db.update(CHILDREN_CLASS_ASSIGNMENTS)
            .set(CHILDREN_CLASS_ASSIGNMENTS.VALID_UNTIL, today.plusDays(1))
            .where(CHILDREN_CLASS_ASSIGNMENTS.ID.eq(assignment))
            .execute();
        assertThat(request("GET", path("/sessions"), member, null).body()).doesNotContain(late.toString());
    }
    @Test
    void staffReviewOfArrivalReferencesDoesNotGrantMemberStationAccessOrRecordAttendance() throws Exception {
        link();
        String route = "/api/children/sessions/" + session + "/arrival-reference/" + child;
        assertThat(request("GET", route, member, null).status()).isEqualTo(403);
        Response review = request("GET", route, admin, null);
        assertThat(review.status()).isEqualTo(200);
        assertThat(review.body()).contains("Child Mabasa").doesNotContain("guardianId", "evidence", "pickupCode");
        assertThat(
            request("GET", "/api/children/sessions/" + session + "/arrival-reference/" + UUID.randomUUID(), admin, null)
                .status())
            .isEqualTo(404);
        assertThat(db.fetchCount(CHILDREN_ATTENDANCE)).isZero();
    }
    @Test
    void staffMustRecheckCurrentPickupAuthorityAfterMemberLabelIssuance() throws Exception {
        link();
        UUID own = selfGrant(), visit = attendanceId(arrival());
        Response issued = myLabel(visit, 0);
        assertThat(issued.status()).isEqualTo(200);
        db.update(CHILDREN_PICKUP_AUTHORIZATIONS)
            .set(CHILDREN_PICKUP_AUTHORIZATIONS.REVOKED_AT, OffsetDateTime.now())
            .where(CHILDREN_PICKUP_AUTHORIZATIONS.ID.eq(own))
            .execute();
        assertThat(
            call("POST", "/attendance/checkout",
                body("attendanceId", visit, "expectedVersion", 1, "requestId", UUID.randomUUID(), "collectorId",
                    guardian, "identityVerified", true, "pickupCode", issued.data().get("pickupCode")))
                .status())
            .isEqualTo(409);
        assertThat(db.select(CHILDREN_ATTENDANCE.CHECKED_OUT_AT)
            .from(CHILDREN_ATTENDANCE)
            .where(CHILDREN_ATTENDANCE.ID.eq(visit))
            .fetchOne(CHILDREN_ATTENDANCE.CHECKED_OUT_AT)).isNull();
    }
}
