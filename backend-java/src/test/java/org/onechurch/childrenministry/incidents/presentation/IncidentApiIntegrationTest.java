package org.onechurch.childrenministry.incidents.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ATTENDANCE_SEGMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_AUDIT;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_INCIDENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_INCIDENT_DETAILS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_INCIDENT_EVENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;

import java.time.Instant;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
class IncidentApiIntegrationTest extends ChildrenIntegrationTestSupport {
    private Map<String, Object> report() throws Exception {
        UUID attendance = attendanceId(arrival());
        UUID segment = db.select(CHILDREN_ATTENDANCE_SEGMENTS.ID)
            .from(CHILDREN_ATTENDANCE_SEGMENTS)
            .where(CHILDREN_ATTENDANCE_SEGMENTS.ATTENDANCE_ID.eq(attendance))
            .fetchOne(CHILDREN_ATTENDANCE_SEGMENTS.ID);
        return body("requestId", UUID.randomUUID(), "childId", child, "sessionId", session, "segmentId", segment,
            "category", "INJURY", "severity", "LOW", "occurredAt", Instant.now(), "description",
            "Private injury detail", "immediateAction", "Private immediate response", "reviewOn", today, "retainUntil",
            today.plusYears(1));
    }
    private void revoke(String... codes) {
        db.deleteFrom(ROLE_PERMISSIONS)
            .where(ROLE_PERMISSIONS.PERMISSION_ID
                .in(db.select(PERMISSIONS.ID).from(PERMISSIONS).where(PERMISSIONS.CODE.in(codes))))
            .execute();
    }
    @Test
    void reportRetriesAreSafeMetadataIsPrivateAndEveryDetailsPageIsAudited() throws Exception {
        Map<String, Object> request = report();
        UUID id = create("/incidents", request);
        assertThat(call("POST", "/incidents", request).data().get("id")).isEqualTo(id.toString());
        assertThat(db.fetchCount(CHILDREN_INCIDENTS)).isEqualTo(1);
        int audits = db.fetchCount(CHILDREN_AUDIT);
        Response list = call("GET", "/incidents?limit=1", null);
        assertThat(list.status()).withFailMessage(list.body()).isEqualTo(200);
        assertThat(list.body()).contains("Child Mabasa", "\"overdue\":1", "\"unassigned\":1")
            .doesNotContain("Private injury", "Private immediate", "description", "note");
        assertThat(db.fetchCount(CHILDREN_AUDIT)).isEqualTo(audits);
        Response access = call("POST", "/incidents/" + id + "/access", body("limit", 1, "offset", 0));
        assertThat(access.body()).contains("Private injury detail", "Private immediate response", "REPORTED");
        assertThat(call("POST", "/incidents/" + id + "/access", body("limit", 1, "offset", 1)).status()).isEqualTo(200);
        assertThat(db.fetchCount(CHILDREN_AUDIT, CHILDREN_AUDIT.ACTION.eq("incident.accessed"))).isEqualTo(2);
        request.put("description", "Changed payload");
        assertThat(call("POST", "/incidents", request).status()).isEqualTo(409);
        assertThat(db.select(CHILDREN_AUDIT.REASON).from(CHILDREN_AUDIT).fetch(CHILDREN_AUDIT.REASON))
            .allMatch(reason -> !reason.contains("Private"));
        assertThat(call("GET", "/attendance-reports?from=" + today + "&until=" + today, null).body())
            .doesNotContain("Private injury", "incident");
    }
    @Test
    void incidentLifecycleKeepsHistoryAndRequiresOwnershipAndCurrentGuardianContact() throws Exception {
        UUID id = create("/incidents", report());
        assertThat(call("POST", "/incidents/" + id + "/follow-up",
            body("expectedVersion", 0, "action", "CLOSED", "note", "Resolved")).status()).isEqualTo(409);
        ok("POST", "/incidents/" + id + "/follow-up", body("expectedVersion", 0, "action", "TRIAGED", "ownerId",
            adminId, "reviewOn", today.plusDays(2), "note", "Private owner triage"));
        assertThat(call("GET", "/incidents/owners?search=Admin", null).status()).isEqualTo(200);
        assertThat(call("GET", "/incidents/" + id + "/guardians", null).body()).contains(guardian.toString())
            .doesNotContain("evidenceReference");
        ok("POST", "/incidents/" + id + "/follow-up", body("expectedVersion", 1, "action", "GUARDIAN_CONTACT",
            "guardianAuthorityId", authority, "note", "Private guardian conversation"));
        ok("POST", "/incidents/" + id + "/follow-up",
            body("expectedVersion", 2, "action", "CLOSED", "note", "Private resolution"));
        assertThat(call("GET", "/incidents", null).body()).contains("\"total\":0");
        assertThat(call("POST", "/incidents/" + id + "/follow-up",
            body("expectedVersion", 3, "action", "ACTION", "note", "Cannot edit closed")).status()).isEqualTo(409);
        ok("POST", "/incidents/" + id + "/follow-up",
            body("expectedVersion", 3, "action", "REOPENED", "note", "New information received"));
        assertThat(db.fetchCount(CHILDREN_INCIDENT_EVENTS)).isEqualTo(5);
        assertThat(call("POST", "/incidents/" + id + "/access", body("limit", 25, "offset", 0)).body())
            .contains("Private guardian conversation", "Private resolution", "New information received");
    }
    @Test
    void staleConcurrentUpdatesCannotLosePrivateHistory() throws Exception {
        UUID id = create("/incidents", report());
        Map<String, Object> action = body("expectedVersion", 0, "action", "TRIAGED", "ownerId", adminId, "reviewOn",
            today.plusDays(1), "note", "Owner review");
        CompletableFuture<Response> first = CompletableFuture.supplyAsync(() -> follow(id, action));
        CompletableFuture<Response> second = CompletableFuture.supplyAsync(() -> follow(id, action));
        assertThat(java.util.List.of(first.join().status(), second.join().status())).containsExactlyInAnyOrder(204,
            409);
        assertThat(db.fetchCount(CHILDREN_INCIDENT_EVENTS)).isEqualTo(2);
    }
    private Response follow(UUID id, Map<String, Object> body) {
        try {
            return call("POST", "/incidents/" + id + "/follow-up", body);
        } catch (Exception e) {
            throw new IllegalStateException(e);
        }
    }
    @Test
    void reportingScopeAndPrivatePermissionsAreIndependent() throws Exception {
        Map<String, Object> request = report();
        revoke("children.incidents.manage", "children.supervise");
        UUID id = create("/incidents", request);
        assertThat(call("GET", "/incidents", null).status()).isEqualTo(403);
        assertThat(call("POST", "/incidents/" + id + "/access", body("limit", 25, "offset", 0)).status())
            .isEqualTo(403);
        assertThat(call("GET", "/sessions/" + session + "/closeout", null).body()).contains("\"incidents\":null");
        ok("PUT", "/sessions/staff",
            body("sessionId", session, "expectedVersion", 2, "workerId", worker, "role", "LEADER", "present", false));
        request.put("requestId", UUID.randomUUID());
        assertThat(call("POST", "/incidents", request).status()).isEqualTo(403);
        assertThat(call("GET", "/incidents/attendance?sessionId=" + session, null).status()).isEqualTo(403);
    }
    @Test
    void invalidAttendanceTimesAndOwnersDoNotCreateOrChangeReports() throws Exception {
        Map<String, Object> request = report();
        request.put("occurredAt", Instant.now().minusSeconds(3600));
        assertThat(call("POST", "/incidents", request).status()).isEqualTo(400);
        request.put("occurredAt", Instant.now().plusSeconds(3600));
        assertThat(call("POST", "/incidents", request).status()).isEqualTo(400);
        request.put("occurredAt", Instant.now());
        UUID id = create("/incidents", request);
        UUID reader = createUser("incidentreader", "incidentreader@example.org", "Incident reader");
        assertThat(call("POST", "/incidents/" + id + "/follow-up", body("expectedVersion", 0, "action", "TRIAGED",
            "ownerId", reader, "reviewOn", today, "note", "Invalid owner")).status()).isEqualTo(400);
        assertThat(db.fetchCount(CHILDREN_INCIDENT_EVENTS)).isEqualTo(1);
        assertThat(call("POST", "/incidents/" + id + "/access", body("limit", 101, "offset", 0)).status())
            .isEqualTo(400);
    }
    @Test
    void closeoutRequiresAcknowledgementAndOpenFollowupSurvivesSessionClosure() throws Exception {
        UUID id = create("/incidents", report());
        UUID attended = db.select(CHILDREN_ATTENDANCE_SEGMENTS.ATTENDANCE_ID)
            .from(CHILDREN_ATTENDANCE_SEGMENTS)
            .fetchOne(CHILDREN_ATTENDANCE_SEGMENTS.ATTENDANCE_ID);
        Response label = label(attended, 0, null);
        String code = (String) label.data().get("pickupCode");
        assertThat(checkout(attended, 1, code, true, UUID.randomUUID()).status()).isEqualTo(200);
        assertThat(call("GET", "/sessions/" + session + "/closeout", null).body()).contains("\"unresolved\":1");
        assertThat(call("PUT", "/sessions/status",
            body("sessionId", session, "expectedVersion", 2, "status", "CLOSED", "reason", "Room checked")).status())
            .isEqualTo(409);
        ok("PUT", "/sessions/status", body("sessionId", session, "expectedVersion", 2, "status", "CLOSED", "reason",
            "Room checked", "acknowledgeIncidents", true));
        assertThat(call("GET", "/incidents?sessionId=" + session, null).body()).contains(id.toString(),
            "\"status\":\"OPEN\"");
    }
    @Test
    void retentionIsEnforcedWithoutReadSidePurges() throws Exception {
        UUID id = create("/incidents", report());
        db.update(CHILDREN_INCIDENTS)
            .set(CHILDREN_INCIDENTS.REVIEW_ON, today.minusDays(2))
            .set(CHILDREN_INCIDENTS.RETAIN_UNTIL, today.minusDays(1))
            .where(CHILDREN_INCIDENTS.ID.eq(id))
            .execute();
        assertThat(call("GET", "/incidents", null).body()).contains("\"total\":0");
        assertThat(call("POST", "/incidents/" + id + "/access", body("limit", 25, "offset", 0)).status())
            .isEqualTo(404);
        assertThat(db.fetchCount(CHILDREN_INCIDENT_DETAILS)).isEqualTo(1);
        assertThat(call("POST", "/incidents/purge-expired", body("limit", 100)).body()).contains("\"purged\":1");
        assertThat(db.fetchCount(CHILDREN_INCIDENT_DETAILS)).isZero();
        assertThat(db.fetchCount(CHILDREN_INCIDENTS)).isEqualTo(1);
        assertThat(db.select(CHILDREN_INCIDENT_EVENTS.NOTE)
            .from(CHILDREN_INCIDENT_EVENTS)
            .fetch(CHILDREN_INCIDENT_EVENTS.NOTE)).containsExactly("");
    }
}
