package org.onechurch.childrenministry.sessions.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_AUDIT;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SERIES_SESSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SESSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SESSION_SERIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SESSION_STAFF;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;

import java.time.LocalDate;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
class SessionSeriesApiIntegrationTest extends ChildrenIntegrationTestSupport {
    private Map<String, Object> draft(List<LocalDate> skipped) {
        return body("name", "Sunday welcome term", "sourceSessionId", session, "sourceVersion", 2, "recurrence",
            body("firstDate", today.plusWeeks(1), "startsAt", "09:00", "endsAt", "10:00", "intervalWeeks", 1,
                "occurrences", 3, "excludedDates", skipped));
    }
    @Test
    void previewDoesNotWriteAndCreationCopiesAnAbsentTeamWithRetrySafety() throws Exception {
        Map<String, Object> draft = draft(List.of(today.plusWeeks(2)));
        int before = db.fetchCount(CHILDREN_SESSIONS);
        Response preview = call("POST", "/session-series/preview", draft);
        assertThat(preview.status()).withFailMessage(preview.body()).isEqualTo(200);
        assertThat(preview.body()).contains("Worker Mabasa", "Africa/Johannesburg", "\"excluded\":true")
            .doesNotContain("evidenceReference", "screenedUntil");
        assertThat(db.fetchCount(CHILDREN_SESSION_SERIES)).isZero();
        assertThat(db.fetchCount(CHILDREN_SESSIONS)).isEqualTo(before);
        Map<String, Object> request = body("requestId", UUID.randomUUID(), "draft", draft);
        Response created = call("POST", "/session-series", request);
        assertThat(created.status()).withFailMessage(created.body()).isEqualTo(201);
        UUID id = UUID.fromString((String) created.data().get("id"));
        assertThat(call("POST", "/session-series", request).data().get("id")).isEqualTo(id.toString());
        assertThat(db.fetchCount(CHILDREN_SESSIONS)).isEqualTo(before + 2);
        List<UUID> ids = ids(id);
        assertThat(db.fetchCount(CHILDREN_SESSION_STAFF,
            CHILDREN_SESSION_STAFF.SESSION_ID.in(ids).and(CHILDREN_SESSION_STAFF.PRESENT.eq(false)))).isEqualTo(2);
        assertThat(call("GET", "/session-series/" + id, null).body()).contains("\"plannedSessions\":2", "PLANNED");
        assertThat(call("GET", "/session-series?limit=1", null).body()).contains(id.toString(), "\"total\":1");
        request.put("draft", draft(List.of()));
        assertThat(call("POST", "/session-series", request).status()).isEqualTo(409);
    }
    @Test
    void aNewClashAfterPreviewRejectsTheWholeSeriesWithoutPartialSessionsOrAudits() throws Exception {
        Map<String, Object> draft = draft(List.of());
        assertThat(call("POST", "/session-series/preview", draft).status()).isEqualTo(200);
        create("/sessions", body("classId", group, "roomId", room, "startsAt",
            today.plusWeeks(2).atTime(9, 0).atZone(java.time.ZoneId.of("Africa/Johannesburg")).toInstant(), "endsAt",
            today.plusWeeks(2).atTime(10, 0).atZone(java.time.ZoneId.of("Africa/Johannesburg")).toInstant(),
            "seatLimit", 2));
        int sessions = db.fetchCount(CHILDREN_SESSIONS), audits = db.fetchCount(CHILDREN_AUDIT);
        assertThat(call("POST", "/session-series/preview", draft).body()).contains("already booked");
        assertThat(call("POST", "/session-series", body("requestId", UUID.randomUUID(), "draft", draft)).status())
            .isEqualTo(409);
        assertThat(db.fetchCount(CHILDREN_SESSIONS)).isEqualTo(sessions);
        assertThat(db.fetchCount(CHILDREN_AUDIT)).isEqualTo(audits);
        assertThat(db.fetchCount(CHILDREN_SESSION_SERIES)).isZero();
    }
    @Test
    void cancellationPreservesAnOpenSessionAndEnforcesSeriesVersions() throws Exception {
        UUID id = create("/session-series", body("requestId", UUID.randomUUID(), "draft", draft(List.of())));
        UUID active = ids(id).getFirst();
        ok("PUT", "/sessions/staff",
            body("sessionId", active, "expectedVersion", 0, "workerId", worker, "role", "LEADER", "present", true));
        ok("PUT", "/sessions/status",
            body("sessionId", active, "expectedVersion", 1, "status", "OPEN", "reason", "Preparing the room"));
        assertThat(
            call("POST", "/session-series/" + id + "/cancel", body("expectedVersion", 9, "reason", "Term change"))
                .status())
            .isEqualTo(409);
        ok("POST", "/session-series/" + id + "/cancel", body("expectedVersion", 0, "reason", "Term change"));
        assertThat(db.select(CHILDREN_SESSIONS.STATUS)
            .from(CHILDREN_SESSIONS)
            .where(CHILDREN_SESSIONS.ID.eq(active))
            .fetchOne(CHILDREN_SESSIONS.STATUS)).isEqualTo("OPEN");
        assertThat(call("GET", "/session-series/" + id, null).body()).contains("\"cancelledSessions\":2",
            "\"stopped\":true");
        assertThat(
            call("POST", "/session-series/" + id + "/cancel", body("expectedVersion", 0, "reason", "Again")).status())
            .isEqualTo(409);
    }
    @Test
    void clearanceWarningsAllowPlanningButDoNotOpenFutureSessions() throws Exception {
        ok("PUT", "/workers", body("personId", worker, "expectedVersion", 0, "screenedUntil", end, "trainedUntil", end,
            "evidenceReference", "renewal pending", "revoked", false));
        Response preview = call("POST", "/session-series/preview", draft(List.of()));
        assertThat(preview.status()).isEqualTo(200);
        assertThat(preview.body()).contains("review identity, account or screening/training");
        UUID id = create("/session-series", body("requestId", UUID.randomUUID(), "draft", draft(List.of())));
        UUID future = ids(id).getFirst();
        assertThat(call("PUT", "/sessions/status",
            body("sessionId", future, "expectedVersion", 0, "status", "OPEN", "reason", "Try opening")).status())
            .isEqualTo(409);
    }
    @Test
    void concurrentRetriesCreateExactlyOneSeries() {
        Map<String, Object> body = body("requestId", UUID.randomUUID(), "draft", draft(List.of()));
        CompletableFuture<Response> one = CompletableFuture.supplyAsync(() -> post(body));
        CompletableFuture<Response> two = CompletableFuture.supplyAsync(() -> post(body));
        Response first = one.join(), second = two.join();
        assertThat(first.status()).withFailMessage(first.body()).isEqualTo(201);
        assertThat(second.status()).withFailMessage(second.body()).isEqualTo(201);
        assertThat(first.data().get("id")).isEqualTo(second.data().get("id"));
        assertThat(db.fetchCount(CHILDREN_SESSION_SERIES)).isEqualTo(1);
        assertThat(db.fetchCount(CHILDREN_SERIES_SESSIONS)).isEqualTo(3);
    }
    @Test
    void readersCannotPlanOrCancelAndInvalidPatternsAreRejected() throws Exception {
        Map<String, Object> stale = draft(List.of());
        stale.put("sourceVersion", 0);
        assertThat(call("POST", "/session-series/preview", stale).status()).isEqualTo(409);
        Map<String, Object> invalid = draft(List.of());
        invalid.put("recurrence",
            body("firstDate", today, "startsAt", "09:00", "endsAt", "10:00", "intervalWeeks", 1, "occurrences", 53));
        assertThat(call("POST", "/session-series/preview", invalid).status()).isEqualTo(400);
        UUID permission = db.select(PERMISSIONS.ID)
            .from(PERMISSIONS)
            .where(PERMISSIONS.CODE.eq("children.manage"))
            .fetchOne(PERMISSIONS.ID);
        db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.eq(permission)).execute();
        assertThat(call("GET", "/session-series", null).status()).isEqualTo(200);
        assertThat(call("POST", "/session-series/preview", draft(List.of())).status()).isEqualTo(403);
        assertThat(
            call("POST", "/session-series", body("requestId", UUID.randomUUID(), "draft", draft(List.of()))).status())
            .isEqualTo(403);
        assertThat(call("POST", "/session-series/" + UUID.randomUUID() + "/cancel",
            body("expectedVersion", 0, "reason", "Change")).status()).isEqualTo(403);
        assertThat(request("GET", "/api/children/session-series", null, null).status()).isEqualTo(401);
    }
    private Response post(Map<String, Object> body) {
        try {
            return call("POST", "/session-series", body);
        } catch (Exception error) {
            throw new IllegalStateException(error);
        }
    }
    private List<UUID> ids(UUID id) {
        return db.select(CHILDREN_SERIES_SESSIONS.SESSION_ID)
            .from(CHILDREN_SERIES_SESSIONS)
            .where(CHILDREN_SERIES_SESSIONS.SERIES_ID.eq(id))
            .orderBy(CHILDREN_SERIES_SESSIONS.SCHEDULED_DATE)
            .fetch(CHILDREN_SERIES_SESSIONS.SESSION_ID);
    }
}
