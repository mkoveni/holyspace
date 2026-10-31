package org.onechurch.childrenministry.sessions.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_AUDIT;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SESSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SESSION_STAFF;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;

import java.time.Instant;
import java.time.ZoneId;
import java.util.Arrays;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
class TeamPlanningApiIntegrationTest extends ChildrenIntegrationTestSupport {
    private UUID future(int day) throws Exception {
        Instant start = today.plusDays(day).atTime(9, 0).atZone(ZoneId.of("Africa/Johannesburg")).toInstant();
        return create("/sessions", body("classId", group, "roomId", room, "startsAt", start, "endsAt",
            start.plusSeconds(3600), "seatLimit", 2));
    }
    private Map<String, Object> draft(UUID first, long version, UUID second) {
        List<Map<String, Object>> targets = second == null ? List.of(body("sessionId", first, "version", version))
            : List.of(body("sessionId", first, "version", version), body("sessionId", second, "version", 0));
        return body("targets", targets, "workerId", worker, "role", "LEADER");
    }
    private String listing(String extra) {
        return "/team-planning?from=" + today + "&until=" + today.plusDays(30) + extra;
    }
    @Test
    void previewIsReadOnlyAndBulkApplyPlansAnAbsentTeamWithGlobalReadinessCounts() throws Exception {
        UUID first = future(7), second = future(14);
        Map<String, Object> draft = draft(first, 0, second);
        int audits = db.fetchCount(CHILDREN_AUDIT);
        Response preview = call("POST", "/team-planning/preview", draft);
        assertThat(preview.status()).withFailMessage(preview.body()).isEqualTo(200);
        assertThat(preview.body()).contains("\"plannedCapacity\":2", "\"ready\":true", "\"blockers\":[]",
            "\"present\":false");
        assertThat(db.fetchCount(CHILDREN_AUDIT)).isEqualTo(audits);
        assertThat(db.fetchCount(CHILDREN_SESSION_STAFF)).isEqualTo(1);
        ok("POST", "/team-planning/apply", body("draft", draft, "reason", "Sunday team agreed"));
        assertThat(db.fetchCount(CHILDREN_SESSION_STAFF,
            CHILDREN_SESSION_STAFF.SESSION_ID.in(first, second).and(CHILDREN_SESSION_STAFF.PRESENT.eq(false))))
            .isEqualTo(2);
        assertThat(db.fetchCount(CHILDREN_SESSIONS,
            CHILDREN_SESSIONS.ID.in(first, second).and(CHILDREN_SESSIONS.VERSION.eq(1L)))).isEqualTo(2);
        Response listing = call("GET", listing("&limit=1&state=READY"), null);
        assertThat(listing.status()).withFailMessage(listing.body()).isEqualTo(200);
        assertThat(listing.body()).contains("\"total\":2", "\"ready\":2", "\"sessions\":2");
        assertThat(call("GET", listing("&search=Worker&offset=1&limit=1"), null).body()).contains("Worker Mabasa",
            "\"total\":2");
        assertThat(call("GET", listing("&state=ATTENTION"), null).body()).contains("\"total\":0", "\"ready\":2");
        assertThat(
            call("POST", "/team-planning/apply", body("draft", draft, "reason", "Retry stale versions")).status())
            .isEqualTo(409);
        assertThat(db.fetchCount(CHILDREN_AUDIT, CHILDREN_AUDIT.ACTION.eq("session.team.planned"))).isEqualTo(2);
    }
    @Test
    void staleSelectionRollsBackAllChangesAndClearanceWarningsStillAllowAdvancePlanning() throws Exception {
        UUID first = future(7), second = future(14);
        ok("PUT", "/sessions/staff",
            body("sessionId", second, "expectedVersion", 0, "workerId", worker, "role", "ASSISTANT", "present", false));
        int audits = db.fetchCount(CHILDREN_AUDIT);
        assertThat(call("POST", "/team-planning/apply", body("draft", draft(first, 0, second), "reason", "Change team"))
            .status()).isEqualTo(409);
        assertThat(db.fetchCount(CHILDREN_SESSION_STAFF, CHILDREN_SESSION_STAFF.SESSION_ID.eq(first))).isZero();
        assertThat(db.fetchCount(CHILDREN_AUDIT)).isEqualTo(audits);
        ok("PUT", "/workers", body("personId", worker, "expectedVersion", 0, "screenedUntil", end, "trainedUntil", end,
            "evidenceReference", "Renewal scheduled", "revoked", false));
        assertThat(call("POST", "/team-planning/preview", draft(first, 0, null)).body())
            .contains("Screening or training", "\"ready\":false", "\"blockers\":[]");
        ok("POST", "/team-planning/apply", body("draft", draft(first, 0, null), "reason", "Renewal before service"));
        assertThat(call("GET", listing("&state=ATTENTION"), null).body()).contains("\"total\":2",
            "\"shortCapacity\":2");
    }
    @Test
    void absentAssignmentsBlockClashesButAdjacentAndCancelledBookingsDoNot() throws Exception {
        UUID first = future(7);
        UUID otherRoom = create("/rooms", body("locationId", location, "name", "Room B", "capacity", 10));
        UUID otherClass = create("/classes",
            body("programmeId", programme, "name", "Seniors", "minAgeMonths", 60, "maxAgeMonths", 156));
        Instant start = today.plusDays(7).atTime(9, 30).atZone(ZoneId.of("Africa/Johannesburg")).toInstant();
        UUID clash = create("/sessions", body("classId", otherClass, "roomId", otherRoom, "startsAt", start, "endsAt",
            start.plusSeconds(3600), "seatLimit", 2));
        ok("PUT", "/sessions/staff",
            body("sessionId", clash, "expectedVersion", 0, "workerId", worker, "role", "LEADER", "present", false));
        assertThat(call("POST", "/team-planning/preview", draft(first, 0, null)).body())
            .contains("overlapping assignment");
        assertThat(
            call("POST", "/team-planning/apply", body("draft", draft(first, 0, null), "reason", "Clash after preview"))
                .status())
            .isEqualTo(409);
        ok("PUT", "/sessions/status",
            body("sessionId", clash, "expectedVersion", 1, "status", "CANCELLED", "reason", "Service cancelled"));
        UUID adjacent = create("/sessions", body("classId", otherClass, "roomId", otherRoom, "startsAt",
            start.plusSeconds(1800), "endsAt", start.plusSeconds(5400), "seatLimit", 2));
        ok("PUT", "/sessions/staff",
            body("sessionId", adjacent, "expectedVersion", 0, "workerId", worker, "role", "LEADER", "present", false));
        assertThat(call("POST", "/team-planning/preview", draft(first, 0, null)).body()).contains("\"blockers\":[]");
        Map<String, Object> both = draft(first, 0, null);
        both.put("targets", List.of(body("sessionId", first, "version", 0), body("sessionId", adjacent, "version", 1)));
        assertThat(call("POST", "/team-planning/preview", both).body()).contains("\"blockers\":[]");
        ok("POST", "/team-planning/apply", body("draft", both, "reason", "Adjacent services"));
    }
    @Test
    void replacementRequiresMembershipAndNeverRewritesConfirmedPresence() throws Exception {
        UUID first = future(7);
        UUID replacement = datedPerson("Replacement", today.minusYears(30));
        UUID account = createUser("replacement", "replacement@example.org", "Replacement");
        ok("POST", "/workers", body("personId", replacement, "accountId", account, "screenedUntil", until,
            "trainedUntil", until, "evidenceReference", "Verified"));
        ok("PUT", "/sessions/staff",
            body("sessionId", first, "expectedVersion", 0, "workerId", worker, "role", "LEADER", "present", false));
        Map<String, Object> draft = draft(first, 1, null);
        draft.put("workerId", replacement);
        draft.put("replaceWorkerId", worker);
        ok("POST", "/team-planning/apply", body("draft", draft, "reason", "Cover confirmed"));
        assertThat(db.fetchCount(CHILDREN_SESSION_STAFF,
            CHILDREN_SESSION_STAFF.SESSION_ID.eq(first).and(CHILDREN_SESSION_STAFF.WORKER_ID.eq(worker)))).isZero();
        assertThat(db.fetchCount(CHILDREN_SESSION_STAFF,
            CHILDREN_SESSION_STAFF.SESSION_ID.eq(first)
                .and(CHILDREN_SESSION_STAFF.WORKER_ID.eq(replacement))
                .and(CHILDREN_SESSION_STAFF.PRESENT.eq(false))))
            .isEqualTo(1);
        ok("PUT", "/sessions/staff",
            body("sessionId", first, "expectedVersion", 2, "workerId", replacement, "role", "LEADER", "present", true));
        Map<String, Object> present = draft(first, 3, null);
        present.put("replaceWorkerId", replacement);
        assertThat(call("POST", "/team-planning/preview", present).body()).contains("Confirmed presence");
        assertThat(
            call("POST", "/team-planning/apply", body("draft", present, "reason", "Should remain present")).status())
            .isEqualTo(409);
    }
    @Test
    void boundsAndWorkerSearchAreExplicit() throws Exception {
        assertThat(call("GET", "/team-planning?from=" + today + "&until=" + today.plusDays(90), null).status())
            .isEqualTo(400);
        assertThat(
            call("POST", "/team-planning/preview", body("targets", List.of(), "workerId", worker, "role", "LEADER"))
                .status())
            .isEqualTo(400);
        Response workers = call("GET", "/team-planning/workers?search=Worker", null);
        assertThat(workers.status()).isEqualTo(200);
        assertThat(workers.body()).contains("Worker Mabasa").doesNotContain("evidenceReference", "accountId");
        assertThat(call("GET", "/team-planning/workers?search=Notthere", null).body()).contains("\"total\":0");
    }
    @Test
    void concurrentPlansForOverlappingSessionsReserveTheWorkerOnlyOnce() throws Exception {
        UUID first = future(7);
        UUID otherRoom = create("/rooms", body("locationId", location, "name", "Parallel room", "capacity", 10));
        UUID otherClass = create("/classes",
            body("programmeId", programme, "name", "Parallel class", "minAgeMonths", 60, "maxAgeMonths", 156));
        Instant start = today.plusDays(7).atTime(9, 0).atZone(ZoneId.of("Africa/Johannesburg")).toInstant();
        UUID second = create("/sessions", body("classId", otherClass, "roomId", otherRoom, "startsAt", start, "endsAt",
            start.plusSeconds(3600), "seatLimit", 2));
        Map<String, Object> together = draft(first, 0, second);
        assertThat(call("POST", "/team-planning/preview", together).body()).contains("overlapping assignment");
        CompletableFuture<Response> one = CompletableFuture.supplyAsync(() -> apply(first));
        CompletableFuture<Response> two = CompletableFuture.supplyAsync(() -> apply(second));
        assertThat(List.of(one.join().status(), two.join().status())).containsExactlyInAnyOrder(204, 409);
        assertThat(db.fetchCount(CHILDREN_SESSION_STAFF, CHILDREN_SESSION_STAFF.SESSION_ID.in(first, second)))
            .isEqualTo(1);
        assertThat(db.fetchCount(CHILDREN_AUDIT, CHILDREN_AUDIT.ACTION.eq("session.team.planned"))).isEqualTo(1);
    }
    private Response apply(UUID target) {
        try {
            return call("POST", "/team-planning/apply",
                body("draft", draft(target, 0, null), "reason", "Concurrent cover"));
        } catch (Exception error) {
            throw new IllegalStateException(error);
        }
    }
    @Test
    void httpBoundariesRejectMissingPermissionsAndNullTargets() throws Exception {
        Map<String, Object> invalid =
            body("targets", Arrays.asList((Object) null), "workerId", worker, "role", "LEADER");
        assertThat(call("POST", "/team-planning/preview", invalid).status()).isEqualTo(400);
        assertThat(request("GET", "/api/children" + listing(""), null, null).status()).isEqualTo(401);
        UUID revoked = db.select(PERMISSIONS.ID)
            .from(PERMISSIONS)
            .where(PERMISSIONS.CODE.eq("children.manage"))
            .fetchOne(PERMISSIONS.ID);
        db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.eq(revoked)).execute();
        assertThat(call("GET", listing(""), null).status()).isEqualTo(403);
        assertThat(call("GET", "/team-planning/workers", null).status()).isEqualTo(403);
        assertThat(call("POST", "/team-planning/preview", draft(session, 2, null)).status()).isEqualTo(403);
        assertThat(
            call("POST", "/team-planning/apply", body("draft", draft(session, 2, null), "reason", "Denied")).status())
            .isEqualTo(403);
    }

}
