package org.onechurch.childrenministry.messaging.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_AUDIT;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_CONSENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_GUARDIAN_AUTHORITIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_GUARDIAN_COMMUNICATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;

import java.time.Instant;
import java.time.OffsetDateTime;
import java.time.ZoneId;
import java.util.Map;
import java.util.UUID;
class GuardianCommunicationApiIntegrationTest extends ChildrenIntegrationTestSupport {
    private Map<String, Object> draft() {
        return body("requestId", UUID.randomUUID(), "childId", child, "authorityId", authority, "programmeId",
            programme, "type", "CONSENT_RENEWAL", "dueOn", today);
    }
    @Test
    void consentMessagesUseSafeWordingAndContactDoesNotCreateConsentOrDeliveryClaims() throws Exception {
        Map<String, Object> request = draft();
        UUID id = create("/guardian-communications", request);
        assertThat(call("POST", "/guardian-communications", request).data().get("id")).isEqualTo(id.toString());
        Response list = call("GET", "/guardian-communications", null);
        assertThat(list.body()).contains("Child Mabasa", "Guardian Mabasa", "\"overdue\":1")
            .doesNotContain("message", "contactReference");
        Response preview = call("GET", "/guardian-communications/" + id, null);
        assertThat(preview.body()).contains("please contact the ministry team", "2026", "\"status\":\"DRAFT\"")
            .doesNotContain("pickupCode", "careInstructions");
        int consents = db.fetchCount(CHILDREN_CONSENTS), audits = db.fetchCount(CHILDREN_AUDIT);
        call("GET", "/guardian-communications/" + id, null);
        assertThat(db.fetchCount(CHILDREN_AUDIT)).isEqualTo(audits);
        ok("POST", "/guardian-communications/" + id + "/contact",
            body("expectedVersion", 0, "status", "CONTACTED", "reference", "Phone conversation confirmed"));
        assertThat(db.fetchCount(CHILDREN_CONSENTS)).isEqualTo(consents);
        assertThat(call("GET", "/guardian-communications?status=CONTACTED", null).body()).contains("\"total\":1");
        assertThat(call("POST", "/guardian-communications/" + id + "/contact",
            body("expectedVersion", 0, "status", "CONTACTED", "reference", "Retry")).status()).isEqualTo(409);
        request.put("dueOn", today.plusDays(1));
        assertThat(call("POST", "/guardian-communications", request).status()).isEqualTo(409);
    }
    @Test
    void authorityRevocationAndPolicyChangeInvalidateDraftContactButAllowCancellation() throws Exception {
        UUID id = create("/guardian-communications", draft());
        create("/consent-policies", body("programmeId", programme, "expectedVersion", 1, "label", "New policy",
            "documentReference", "current-policy", "contentHash", "b".repeat(64)));
        assertThat(call("GET", "/guardian-communications/" + id, null).status()).isEqualTo(409);
        assertThat(call("POST", "/guardian-communications/" + id + "/contact",
            body("expectedVersion", 0, "status", "CONTACTED", "reference", "Outdated message")).status())
            .isEqualTo(409);
        ok("POST", "/guardian-communications/" + id + "/contact",
            body("expectedVersion", 0, "status", "CANCELLED", "reference", "Policy replaced"));
        UUID renewed = create("/guardian-communications", draft());
        db.update(CHILDREN_GUARDIAN_AUTHORITIES)
            .set(CHILDREN_GUARDIAN_AUTHORITIES.REVOKED_AT, OffsetDateTime.now())
            .where(CHILDREN_GUARDIAN_AUTHORITIES.ID.eq(authority))
            .execute();
        assertThat(call("GET", "/guardian-communications/" + renewed, null).status()).isEqualTo(409);
        assertThat(call("POST", "/guardian-communications/" + renewed + "/contact",
            body("expectedVersion", 0, "status", "CONTACTED", "reference", "Revoked guardian")).status())
            .isEqualTo(409);
        ok("POST", "/guardian-communications/" + renewed + "/contact",
            body("expectedVersion", 0, "status", "CANCELLED", "reference", "Authority revoked"));
    }
    @Test
    void sessionRemindersRequirePlacementAndAFutureSession() throws Exception {
        Instant starts = today.plusDays(7).atTime(9, 0).atZone(ZoneId.of("Africa/Johannesburg")).toInstant();
        UUID upcoming = create("/sessions", body("classId", group, "roomId", room, "startsAt", starts, "endsAt",
            starts.plusSeconds(3600), "seatLimit", 2));
        Map<String, Object> request = draft();
        request.put("type", "SESSION_REMINDER");
        request.put("sessionId", upcoming);
        UUID id = create("/guardian-communications", request);
        assertThat(call("GET", "/guardian-communications/" + id, null).body()).contains("scheduled for",
            "does not confirm admission");
        UUID other = datedPerson("Unplaced", today.minusYears(8));
        UUID guardianAuthority = create("/guardian-authorities", body("childId", other, "guardianId", guardian,
            "validFrom", from, "validUntil", until, "evidenceReference", "Verified"));
        enrol(other);
        request.put("requestId", UUID.randomUUID());
        request.put("childId", other);
        request.put("authorityId", guardianAuthority);
        assertThat(call("POST", "/guardian-communications", request).status()).isEqualTo(409);
        request.put("sessionId", session);
        assertThat(call("POST", "/guardian-communications", request).status()).isEqualTo(409);
        assertThat(db.fetchCount(CHILDREN_GUARDIAN_COMMUNICATIONS)).isEqualTo(1);
    }
    @Test
    void onlySafeguardingUsersCanPrepareAndRecordContact() throws Exception {
        UUID permission = db.select(PERMISSIONS.ID)
            .from(PERMISSIONS)
            .where(PERMISSIONS.CODE.eq("children.safeguarding"))
            .fetchOne(PERMISSIONS.ID);
        db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.eq(permission)).execute();
        assertThat(call("GET", "/guardian-communications", null).status()).isEqualTo(403);
        assertThat(call("POST", "/guardian-communications", draft()).status()).isEqualTo(403);
        assertThat(request("GET", "/api/children/guardian-communications", null, null).status()).isEqualTo(401);
    }
}
