package org.onechurch.childrenministry.sessions.presentation;
import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;

import java.util.UUID;
class SessionCloseoutApiIntegrationTest extends ChildrenIntegrationTestSupport {
    @Test
    void closeoutTracksWarningsAndSafeClosureWithoutExposingPrivateData() throws Exception {
        UUID visit = attendanceId(arrival());
        Response pending = summary(session);
        assertThat(pending.status()).isEqualTo(200);
        assertThat(pending.data()).containsEntry("visits", 1)
            .containsEntry("children", 1)
            .containsEntry("awaitingCollection", 1)
            .containsEntry("labelsNeedingAttention", 1);
        assertThat(pending.body()).doesNotContain("labelHash", "pickupCode", "evidenceReference", "collectorId",
            "exceptionReason");
        assertThat(call("GET", "/station/sessions/" + session + "/day?view=ATTENDING", null).body())
            .contains("Issue a pickup label before collection.");
        assertThat(close(2).status()).isEqualTo(409);
        Response issued = label(visit, 0, "");
        assertThat(summary(session).data()).containsEntry("labelsNeedingAttention", 0);
        assertThat(checkout(visit, 1, "wrong-code", true, UUID.randomUUID()).status()).isEqualTo(400);
        assertThat(summary(session).data()).containsEntry("failedHandoverAttempts", 1);
        assertThat(call("GET", "/station/sessions/" + session + "/day?view=ATTENDING", null).body())
            .contains("Review unsuccessful pickup-code attempts before handover.");
        assertThat(checkout(visit, 2, (String) issued.data().get("pickupCode"), true, UUID.randomUUID()).status())
            .isEqualTo(200);
        assertThat(summary(session).data()).containsEntry("collected", 1)
            .containsEntry("awaitingCollection", 0)
            .containsEntry("failedHandoverAttempts", 0);
        assertThat(close(0).status()).isEqualTo(409);
        assertThat(close(2).status()).isEqualTo(204);
        assertThat(summary(session).data()).containsEntry("collected", 1);
        assertThat(arrive(child, session, UUID.randomUUID()).status()).isEqualTo(409);
    }
    @Test
    void supervisorCollectionsAreReportedAfterTheirWarningsAreResolved() throws Exception {
        UUID visit = attendanceId(arrival());
        Response result = call("POST", "/attendance/supervisor-checkout",
            body("attendanceId", visit, "expectedVersion", 0, "requestId", UUID.randomUUID(), "collectorId", collector,
                "identityVerified", true, "reason", "Label lost; identity and authority verified"));
        assertThat(result.status()).withFailMessage(result.body()).isEqualTo(200);
        assertThat(summary(session).data()).containsEntry("supervisorCollections", 1)
            .containsEntry("labelsNeedingAttention", 0)
            .containsEntry("collected", 1);
    }
    @Test
    void transferredVisitsRemainInSourceSummaryAfterCollectionAtDestination() throws Exception {
        UUID visit = attendanceId(arrival());
        UUID nextGroup = create("/classes",
            body("programmeId", programme, "name", "Next class", "minAgeMonths", 60, "maxAgeMonths", 156));
        UUID nextRoom = create("/rooms", body("locationId", location, "name", "Room B", "capacity", 10));
        UUID next = newSession(nextGroup, nextRoom, 2);
        worker = datedPerson("Second worker", today.minusYears(30));
        UUID account = createUser("worker2", "worker2@church.org", "Worker Two");
        ok("POST", "/workers", body("personId", worker, "accountId", account, "screenedUntil", until, "trainedUntil",
            until, "evidenceReference", "verified/second"));
        open(next);
        ok("POST", "/class-assignments/end", body("assignmentId", assignment, "expectedVersion", 0));
        assign(child, enrolment, nextGroup);
        assertThat(call("POST", "/attendance/transfers",
            body("attendanceId", visit, "expectedVersion", 0, "targetSessionId", next, "reason", "Class move"))
            .status()).isEqualTo(200);
        assertThat(summary(session).data()).containsEntry("visits", 1)
            .containsEntry("transferredOut", 1)
            .containsEntry("collected", 0)
            .containsEntry("awaitingCollection", 0);
        Response issued = label(visit, 1, "Replacement after transfer");
        assertThat(checkout(visit, 2, (String) issued.data().get("pickupCode"), true, UUID.randomUUID()).status())
            .isEqualTo(200);
        assertThat(summary(session).data()).containsEntry("transferredOut", 1).containsEntry("collected", 0);
        assertThat(summary(next).data()).containsEntry("collected", 1).containsEntry("transferredOut", 0);
        assertThat(close(2).status()).isEqualTo(204);
    }
    private Response summary(UUID id) throws Exception {
        return call("GET", "/sessions/" + id + "/closeout", null);
    }
    private Response close(long version) throws Exception {
        return call("PUT", "/sessions/status", body("sessionId", session, "expectedVersion", version, "status",
            "CLOSED", "reason", "Room clear, every child accounted for"));
    }
}
