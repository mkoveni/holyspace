package org.onechurch.events.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_EMAIL_DELIVERIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_CLOSEOUTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_CLOSEOUT_HISTORY;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_OCCURRENCES;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.EventDayIntegrationTestSupport;

import java.time.OffsetDateTime;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
class EventCloseoutApiIntegrationTest extends EventDayIntegrationTestSupport {
    private String path(UUID id) {
        return ROOT + "/occurrences/" + id;
    }
    private Response readiness(UUID id) throws Exception {
        return request("GET", path(id) + "/readiness", admin, null);
    }
    private String hash(UUID id) throws Exception {
        Response response = readiness(id);
        assertThat(response.status()).withFailMessage(response.body()).isEqualTo(200);
        return (String) ((Map<?, ?>) response.data().get("review")).get("hash");
    }
    private Map<String, Object> closeBody(UUID id, long version, String followUp) throws Exception {
        return Map.of("expectedVersion", version, "reviewHash", hash(id), "note", "Attendance reconciled with team",
            "followUp", followUp, "attendanceReviewed", true, "followUpReviewed", true);
    }
    private void ended(UUID id) {
        db.update(EVENT_OCCURRENCES)
            .set(EVENT_OCCURRENCES.STARTS_AT, OffsetDateTime.now().minusHours(2))
            .set(EVENT_OCCURRENCES.ENDS_AT, OffsetDateTime.now().minusHours(1))
            .where(EVENT_OCCURRENCES.ID.eq(id))
            .execute();
    }
    @Test
    void readinessIsReadOnlyAndUnplannedStaffingNeedsReview() throws Exception {
        UUID occurrence = nearOccurrence(2);
        Response first = readiness(occurrence);
        assertThat(first.status()).withFailMessage(first.body()).isEqualTo(200);
        assertThat(first.body()).contains("No duties planned").doesNotContain("recipient", "password");
        assertThat(((Map<?, ?>) first.data().get("closeout")).get("version")).isEqualTo(0);
        assertThat(((Map<?, ?>) first.data().get("review")).get("canClose")).isEqualTo(false);
        assertThat(db.fetchCount(EVENT_CLOSEOUTS)).isZero();
        assertThat(hash(occurrence)).isEqualTo(hash(occurrence));
    }
    @Test
    void closingRequiresAnEndedOccurrenceAndExplicitReview() throws Exception {
        UUID occurrence = nearOccurrence(2);
        assertThat(request("POST", path(occurrence) + "/closeout", admin, closeBody(occurrence, 0, "")).status())
            .isEqualTo(409);
        ended(occurrence);
        Map<String, Object> body = new java.util.HashMap<>(closeBody(occurrence, 0, ""));
        body.put("attendanceReviewed", false);
        assertThat(request("POST", path(occurrence) + "/closeout", admin, body).status()).isEqualTo(400);
        body.put("attendanceReviewed", true);
        assertThat(request("POST", path(occurrence) + "/closeout", admin, body).status()).isEqualTo(204);
        assertThat(db.selectFrom(EVENT_CLOSEOUTS).fetchOne().getStatus()).isEqualTo("CLOSED");
        assertThat(request("POST", path(occurrence) + "/closeout", admin, body).status()).isEqualTo(409);
    }
    @Test
    void waitlistsNeedRecordedFollowUpAndHistoryKeepsReviewedTotals() throws Exception {
        UUID occurrence = nearOccurrence(1);
        register(occurrence, person("Confirmed"));
        register(occurrence, person("Waitlisted"));
        ended(occurrence);
        assertThat(request("POST", path(occurrence) + "/closeout", admin, closeBody(occurrence, 0, "")).status())
            .isEqualTo(400);
        assertThat(request("POST", path(occurrence) + "/closeout", admin,
            closeBody(occurrence, 0, "Coordinator will contact the waitlisted family tomorrow")).status())
            .isEqualTo(204);
        assertThat(db.selectFrom(EVENT_CLOSEOUT_HISTORY).fetchOne().getWaitlisted()).isEqualTo(1);
        assertThat(db.selectFrom(EVENT_CLOSEOUT_HISTORY).fetchOne().getAbsent()).isEqualTo(1);
        assertThat(request("GET", path(occurrence) + "/closeout/history", admin, null).body())
            .contains("Coordinator will contact", adminId.toString());
    }
    @Test
    void staleReviewsAndConcurrentClosuresCannotOverwriteEvidence() throws Exception {
        UUID occurrence = nearOccurrence(2), booking = register(occurrence, person("Expected"));
        Map<String, Object> old = closeBody(occurrence, 0, "");
        created(arrive(booking, UUID.randomUUID()));
        ended(occurrence);
        assertThat(request("POST", path(occurrence) + "/closeout", admin, old).status()).isEqualTo(409);
        Map<String, Object> current = closeBody(occurrence, 0, "");
        CompletableFuture<Response> one = CompletableFuture.supplyAsync(() -> closeUnchecked(occurrence, current));
        CompletableFuture<Response> two = CompletableFuture.supplyAsync(() -> closeUnchecked(occurrence, current));
        assertThat(List.of(one.join().status(), two.join().status())).containsExactlyInAnyOrder(204, 409);
        assertThat(db.fetchCount(EVENT_CLOSEOUT_HISTORY)).isEqualTo(1);
    }
    @Test
    void laterAttendanceCorrectionsFlagTheCloseoutWithoutErasingItsSnapshot() throws Exception {
        UUID occurrence = nearOccurrence(2), booking = register(occurrence, person("Present"));
        created(arrive(booking, UUID.randomUUID()));
        ended(occurrence);
        assertThat(request("POST", path(occurrence) + "/closeout", admin, closeBody(occurrence, 0, "")).status())
            .isEqualTo(204);
        assertThat(readiness(occurrence).data().get("changedSinceCloseout")).isEqualTo(false);
        assertThat(request("POST", ROOT + "/registrations/" + booking + "/attendance-correction", admin,
            Map.of("expectedVersion", 0, "status", "VOIDED", "reason", "Wrong attendee selected")).status())
            .isEqualTo(204);
        assertThat(readiness(occurrence).data().get("changedSinceCloseout")).isEqualTo(true);
        assertThat(db.selectFrom(EVENT_CLOSEOUT_HISTORY).fetchOne().getAttended()).isEqualTo(1);
        assertThat(request("POST", path(occurrence) + "/closeout/reopen", admin,
            Map.of("expectedVersion", 1, "reason", "Review corrected attendance")).status()).isEqualTo(204);
        assertThat(request("POST", path(occurrence) + "/closeout", admin, closeBody(occurrence, 2, "")).status())
            .isEqualTo(204);
        assertThat(db.fetchCount(EVENT_CLOSEOUT_HISTORY)).isEqualTo(3);
        assertThat(readiness(occurrence).data().get("changedSinceCloseout")).isEqualTo(false);
    }
    @Test
    void cancelledOccurrencesCanBeClosedAndReopeningRequiresCurrentVersionAndReason() throws Exception {
        UUID occurrence = nearOccurrence(2);
        Map<String, Object> date =
            (Map<String, Object>) request("GET", path(occurrence), admin, null).data().get("occurrence");
        assertThat(apply(change(date, "CANCEL", "THIS", date.get("originDate").toString(), "09:00", venue)).status())
            .isEqualTo(204);
        assertThat(request("POST", path(occurrence) + "/closeout", admin, closeBody(occurrence, 0, "")).status())
            .isEqualTo(204);
        assertThat(request("POST", path(occurrence) + "/closeout/reopen", admin,
            Map.of("expectedVersion", 0, "reason", "Check follow-up")).status()).isEqualTo(409);
        assertThat(
            request("POST", path(occurrence) + "/closeout/reopen", admin, Map.of("expectedVersion", 1, "reason", " "))
                .status())
            .isEqualTo(400);
        assertThat(db.fetchCount(EVENT_CLOSEOUT_HISTORY)).isEqualTo(1);
    }
    @Test
    void communicationOutcomesAreScopedAndCapturedMailNeedsFollowUp() throws Exception {
        UUID occurrence = nearOccurrence(2), person = person("Notified"), booking = register(occurrence, person);
        UUID contact = created(request("POST", "/api/people/" + person + "/contacts", admin,
            Map.of("expectedVersion", 0, "kind", "EMAIL", "value", "attendee@example.org", "primary", true)));
        Response preview = request("POST", ROOT + "/registrations/" + booking + "/notification-review", admin,
            Map.of("contactId", contact, "purpose", "REMINDER", "message", "See you there"));
        UUID delivery = created(request("POST", ROOT + "/registrations/" + booking + "/notifications", admin,
            Map.of("requestId", UUID.randomUUID(), "contactId", contact, "purpose", "REMINDER", "message",
                "See you there", "reviewHash", preview.data().get("hash"), "verificationEvidence",
                "Recipient verified own email")));
        Response review = readiness(occurrence);
        Map<?, ?> notices = (Map<?, ?>) ((Map<?, ?>) review.data().get("review")).get("notices");
        assertThat(notices.get("pending")).isEqualTo(1);
        assertThat(notices.get("remindersApproved")).isEqualTo(1);
        db.update(COMMUNICATION_EMAIL_DELIVERIES)
            .set(COMMUNICATION_EMAIL_DELIVERIES.STATUS, "SENT")
            .set(COMMUNICATION_EMAIL_DELIVERIES.CAPTURED, true)
            .set(COMMUNICATION_EMAIL_DELIVERIES.VERSION, 1L)
            .where(COMMUNICATION_EMAIL_DELIVERIES.ID.eq(delivery))
            .execute();
        ended(occurrence);
        assertThat(request("POST", path(occurrence) + "/closeout", admin, closeBody(occurrence, 0, "")).status())
            .isEqualTo(400);
        assertThat(request("POST", path(occurrence) + "/closeout", admin,
            closeBody(occurrence, 0, "Contact attendee directly tomorrow")).status()).isEqualTo(204);
        assertThat(readiness(occurrence).body()).doesNotContain("attendee@example.org", "See you there");
        UUID other = nearOccurrence(1);
        assertThat(((Map<?, ?>) ((Map<?, ?>) readiness(other).data().get("review")).get("notices")).get("approved"))
            .isEqualTo(0);
    }
    @Test
    void operationsRequireLiveManagementPermissionAndHistoryIsBounded() throws Exception {
        UUID occurrence = nearOccurrence(2);
        assertThat(request("GET", path(occurrence) + "/closeout/history?limit=101", admin, null).status())
            .isEqualTo(400);
        assertThat(request("GET", path(occurrence) + "/readiness", null, null).status()).isEqualTo(401);
        db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.ROLE_ID.eq(ADMIN_ROLE)).execute();
        assertThat(readiness(occurrence).status()).isEqualTo(403);
    }
    private Response closeUnchecked(UUID occurrence, Map<String, Object> body) {
        try {
            return request("POST", path(occurrence) + "/closeout", admin, body);
        } catch (Exception e) {
            throw new IllegalStateException(e);
        }
    }
}
