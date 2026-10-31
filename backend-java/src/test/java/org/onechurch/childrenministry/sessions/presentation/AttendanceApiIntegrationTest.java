package org.onechurch.childrenministry.sessions.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ATTENDANCE;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ATTENDANCE_SEGMENTS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;

import java.util.UUID;
class AttendanceApiIntegrationTest extends ChildrenIntegrationTestSupport {

    @Test
    void arrivalAndCheckoutAreIdempotentAndSecretsAreNeverReturnedByReads() throws Exception {
        UUID requestId = UUID.randomUUID();
        Response first = arrive(child, session, requestId);
        assertThat(first.status()).isEqualTo(200);
        UUID attendance = attendanceId(first);
        assertThat(arrive(child, session, requestId).data().get("id")).isEqualTo(attendance.toString());
        assertThat(arrive(child, session, UUID.randomUUID()).status()).isEqualTo(409);
        Response issued = label(attendance, 0, "");
        assertThat(issued.status()).isEqualTo(200);
        String code = (String) issued.data().get("pickupCode");
        assertThat(code).hasSize(22);
        Response read = call("GET", "/attendance/" + attendance, null);
        assertThat(read.body()).doesNotContain(code, "labelHash", "failedAttempts");
        assertThat(db.select(CHILDREN_ATTENDANCE.LABEL_HASH)
            .from(CHILDREN_ATTENDANCE)
            .where(CHILDREN_ATTENDANCE.ID.eq(attendance))
            .fetchOne(CHILDREN_ATTENDANCE.LABEL_HASH)).isNotEqualTo(code).hasSize(64);
        UUID checkoutRequest = UUID.randomUUID();
        assertThat(checkout(attendance, 1, code, false, checkoutRequest).status()).isEqualTo(400);
        Response collected = checkout(attendance, 1, code, true, checkoutRequest);
        assertThat(collected.status()).isEqualTo(200);
        assertThat(collected.data().get("checkedOutAt")).isNotNull();
        assertThat(checkout(attendance, 1, code, true, checkoutRequest).status()).isEqualTo(200);
        assertThat(checkout(attendance, 2, code, true, UUID.randomUUID()).status()).isEqualTo(409);
        assertThat(db.fetchCount(CHILDREN_ATTENDANCE_SEGMENTS, CHILDREN_ATTENDANCE_SEGMENTS.LEFT_AT.isNull())).isZero();
    }
    @Test
    void replacementInvalidatesPreviousLabelAndFailedAttemptsPersist() throws Exception {
        UUID attendance = attendanceId(arrival());
        String first = (String) label(attendance, 0, "").data().get("pickupCode");
        assertThat(label(attendance, 1, "").status()).isEqualTo(400);
        Response replacement = label(attendance, 1, "Original label lost");
        assertThat(replacement.status()).isEqualTo(200);
        assertThat(checkout(attendance, 2, first, true, UUID.randomUUID()).status()).isEqualTo(400);
        assertThat(call("GET", "/attendance/" + attendance, null).data().get("version")).isEqualTo(3);
        assertThat(
            checkout(attendance, 3, (String) replacement.data().get("pickupCode"), true, UUID.randomUUID()).status())
            .isEqualTo(200);
    }
    @Test
    void repeatedBadCodesAreThrottledAndSupervisorMustStillVerifyIdentity() throws Exception {
        UUID attendance = attendanceId(arrival());
        label(attendance, 0, "");
        for (int attempt = 0; attempt < 5; attempt++) {
            assertThat(checkout(attendance, attempt + 1, "bad-code", true, UUID.randomUUID()).status())
                .isEqualTo(attempt == 4 ? 429 : 400);
        }
        assertThat(checkout(attendance, 6, "bad-code", true, UUID.randomUUID()).status()).isEqualTo(429);
        assertThat(call("POST", "/attendance/supervisor-checkout",
            body("attendanceId", attendance, "expectedVersion", 6, "requestId", UUID.randomUUID(), "collectorId",
                collector, "identityVerified", false, "reason", "Lost label"))
            .status()).isEqualTo(400);
        Response result = call("POST", "/attendance/supervisor-checkout",
            body("attendanceId", attendance, "expectedVersion", 6, "requestId", UUID.randomUUID(), "collectorId",
                collector, "identityVerified", true, "reason", "Lost label; identity independently checked"));
        assertThat(result.status()).isEqualTo(200);
        assertThat(result.data().get("exceptionUsed")).isEqualTo(true);
        assertThat(call("GET", "/attendance/" + attendance + "/events", null).body()).contains("SUPERVISOR_COLLECTION");
    }
    @Test
    void transfersRevalidateAdmissionAndPreserveSegmentsWhileInvalidatingLabels() throws Exception {
        UUID arrivalRequest = UUID.randomUUID();
        UUID attendance = attendanceId(arrive(child, session, arrivalRequest));
        label(attendance, 0, "");
        UUID nextGroup = create("/classes",
            body("programmeId", programme, "name", "Next class", "minAgeMonths", 60, "maxAgeMonths", 156));
        UUID nextRoom = create("/rooms", body("locationId", location, "name", "Room B", "capacity", 10));
        UUID next = newSession(nextGroup, nextRoom, 2);
        UUID nextWorker = datedPerson("Second worker", today.minusYears(30));
        UUID nextAccount = createUser("worker2", "worker2@church.org", "Worker Two");
        ok("POST", "/workers", body("personId", nextWorker, "accountId", nextAccount, "screenedUntil", until,
            "trainedUntil", until, "evidenceReference", "verified/second"));
        UUID previousWorker = worker;
        worker = nextWorker;
        open(next);
        worker = previousWorker;
        assertThat(call("POST", "/attendance/transfers",
            body("attendanceId", attendance, "expectedVersion", 1, "targetSessionId", next, "reason", "Class move"))
            .status()).isEqualTo(409);
        ok("POST", "/class-assignments/end", body("assignmentId", assignment, "expectedVersion", 0));
        assign(child, enrolment, nextGroup);
        Response result = call("POST", "/attendance/transfers",
            body("attendanceId", attendance, "expectedVersion", 1, "targetSessionId", next, "reason", "Class move"));
        assertThat(result.status()).isEqualTo(200);
        assertThat(result.data().get("labelIssued")).isEqualTo(false);
        assertThat(arrive(child, session, arrivalRequest).status()).isEqualTo(200);
        assertThat(
            db.fetchCount(CHILDREN_ATTENDANCE_SEGMENTS, CHILDREN_ATTENDANCE_SEGMENTS.ATTENDANCE_ID.eq(attendance)))
            .isEqualTo(2);
    }

    @Test
    void stationListsExpectedChildrenAndCurrentCollectorsWithoutEvidence() throws Exception {
        Response expected = call("GET", "/sessions/" + session + "/expected-children", null);
        assertThat(expected.status()).isEqualTo(200);
        assertThat(expected.body()).contains(child.toString(), "Child Mabasa");
        UUID attendance = attendanceId(arrival());
        Response collectors = call("GET", "/attendance/" + attendance + "/collectors", null);
        assertThat(collectors.status()).isEqualTo(200);
        assertThat(collectors.body()).contains(collector.toString(), "Collector Mabasa")
            .doesNotContain("signed/pickup", "verified/guardian");
        ok("POST", "/pickup-authorizations/revoke", body("id", grant, "expectedVersion", 0, "reason", "Withdrawn"));
        assertThat(call("GET", "/attendance/" + attendance + "/collectors", null).body()).isEqualTo("[]");
    }
}
