package org.onechurch.childrenministry.sessions.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;

import java.time.Instant;
import java.util.UUID;
class SessionApiIntegrationTest extends ChildrenIntegrationTestSupport {

    @Test
    void effectiveCapacityUsesPresentQualifiedStaffAndRoomLimit() throws Exception {
        Response view = call("GET", "/sessions/" + session, null);
        assertThat(view.status()).isEqualTo(200);
        assertThat(view.data().get("effectiveCapacity")).isEqualTo(2);
        ok("PUT", "/sessions/staff",
            body("sessionId", session, "expectedVersion", 2, "workerId", worker, "role", "LEADER", "present", false));
        Response changed = call("GET", "/sessions/" + session, null);
        assertThat(changed.data().get("effectiveCapacity")).isEqualTo(0);
        assertThat(changed.body()).contains("NEEDS_REVIEW");
        assertThat(arrive(child, session, UUID.randomUUID()).status()).isEqualTo(409);
    }
    @Test
    void revokedScreeningStopsNewAdmissionsWithoutTrappingAttendingChildren() throws Exception {
        UUID attendance = attendanceId(arrival());
        Response issued = label(attendance, 0, "");
        ok("PUT", "/workers", body("personId", worker, "expectedVersion", 0, "screenedUntil", until, "trainedUntil",
            until, "evidenceReference", "revoked/assessment", "revoked", true));
        assertThat(call("GET", "/sessions/" + session, null).data().get("effectiveCapacity")).isEqualTo(0);
        assertThat(checkout(attendance, 1, (String) issued.data().get("pickupCode"), true, UUID.randomUUID()).status())
            .isEqualTo(200);
    }
    @Test
    void roomAndClassConflictsAreDatabaseEnforcedAndStaleStaffingRejected() throws Exception {
        assertThat(call("POST", "/sessions",
            body("classId", group, "roomId", room, "startsAt", Instant.now(), "endsAt", end, "seatLimit", 2)).status())
            .isEqualTo(409);
        assertThat(call("PUT", "/sessions/staff",
            body("sessionId", session, "expectedVersion", 0, "workerId", worker, "role", "LEADER", "present", true))
            .status()).isEqualTo(409);
    }
    @Test
    void activeChildrenPreventSessionClosure() throws Exception {
        arrival();
        assertThat(call("PUT", "/sessions/status",
            body("sessionId", session, "expectedVersion", 2, "status", "CLOSED", "reason", "Close")).status())
            .isEqualTo(409);
    }

    @Test
    void aWorkerCannotBePresentInTwoOverlappingSessions() throws Exception {
        UUID otherClass = create("/classes",
            body("programmeId", programme, "name", "Other class", "minAgeMonths", 60, "maxAgeMonths", 156));
        UUID otherRoom = create("/rooms", body("locationId", location, "name", "Other room", "capacity", 10));
        UUID other = newSession(otherClass, otherRoom, 2);
        assertThat(call("PUT", "/sessions/staff",
            body("sessionId", other, "expectedVersion", 0, "workerId", worker, "role", "LEADER", "present", true))
            .status()).isEqualTo(409);
        ok("PUT", "/sessions/staff",
            body("sessionId", session, "expectedVersion", 2, "workerId", worker, "role", "LEADER", "present", false));
        ok("PUT", "/sessions/staff",
            body("sessionId", other, "expectedVersion", 0, "workerId", worker, "role", "LEADER", "present", true));
    }

    @Test
    void ordinaryStationOperatorsAreRestrictedToTheirPresentAssignments() throws Exception {
        UUID otherClass = create("/classes",
            body("programmeId", programme, "name", "Private class", "minAgeMonths", 60, "maxAgeMonths", 156));
        UUID otherRoom = create("/rooms", body("locationId", location, "name", "Private room", "capacity", 10));
        UUID other = newSession(otherClass, otherRoom, 2);
        UUID permission = db.select(PERMISSIONS.ID)
            .from(PERMISSIONS)
            .where(PERMISSIONS.CODE.eq("children.supervise"))
            .fetchOne(PERMISSIONS.ID);
        db.deleteFrom(ROLE_PERMISSIONS).where(ROLE_PERMISSIONS.PERMISSION_ID.eq(permission)).execute();
        assertThat(call("GET", "/sessions/" + session + "/roster", null).status()).isEqualTo(200);
        assertThat(call("GET", "/sessions/" + other + "/roster", null).status()).isEqualTo(403);
        assertThat(call("GET", "/sessions/" + other + "/expected-children", null).status()).isEqualTo(403);
    }

    @Test
    void locationRequiresARealTimeZone() throws Exception {
        assertThat(call("POST", "/locations", body("name", "Other", "timeZone", null)).status()).isEqualTo(400);
        assertThat(call("POST", "/locations", body("name", "Other", "timeZone", "Unknown/Zone")).status())
            .isEqualTo(400);
    }
}
