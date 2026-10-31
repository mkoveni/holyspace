package org.onechurch.childrenministry.sessions.presentation;

import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_TEAMS;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_VOLUNTEERS;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;

import java.util.List;
import java.util.UUID;

class ServingStaffApiIntegrationTest extends ChildrenIntegrationTestSupport {
    private UUID volunteer, team, role, shift, booking;
    @BeforeEach
    void servingPlan() throws Exception {
        db.truncate(SERVING_TEAMS).cascade().execute();
        db.truncate(SERVING_VOLUNTEERS).cascade().execute();
        for (String code : List.of("volunteering.manage", "volunteering.read")) {
            UUID id = UUID.randomUUID();
            db.insertInto(PERMISSIONS).set(PERMISSIONS.ID, id).set(PERMISSIONS.CODE, code).execute();
            db.insertInto(ROLE_PERMISSIONS)
                .set(ROLE_PERMISSIONS.ROLE_ID, ADMIN_ROLE)
                .set(ROLE_PERMISSIONS.PERMISSION_ID, id)
                .execute();
        }
        volunteer = addServing("/volunteers", body("personId", worker));
        team = addServing("/teams", body("name", "Children volunteers"));
        role = addServing("/teams/" + team + "/roles", body("expectedVersion", 0, "name", "Session leader",
            "childrenRole", "LEADER", "requiredCredentials", List.of()));
        addServing("/teams/" + team + "/memberships",
            body("expectedVersion", 1, "volunteerId", volunteer, "validFrom", from, "validUntil", until));
        addServing("/teams/" + team + "/approvals", body("expectedVersion", 2, "volunteerId", volunteer, "roleId", role,
            "validFrom", from, "validUntil", until));
        assertThat(serving("PUT", "/volunteers/" + volunteer + "/availability", body("expectedVersion", 0, "weekly",
            List.of(), "exceptions", List.of(body("startsAt", from, "endsAt", until, "available", true)))).status())
            .isEqualTo(204);
        shift = addServing("/shifts", body("requestId", UUID.randomUUID(), "teamId", team, "roleId", role, "sourceKind",
            "CHILDREN_SESSION", "sourceId", session, "startsAt", from, "endsAt", end.plusSeconds(60), "positions", 1));
        booking = addServing("/assignments", body("requestId", UUID.randomUUID(), "shiftId", shift, "expectedVersion",
            0, "volunteerId", volunteer, "status", "CONFIRMED"));
    }
    private Response serving(String method, String path, Object body) throws Exception {
        return request(method, "/api/volunteering" + path, admin, body);
    }
    private UUID addServing(String path, Object body) throws Exception {
        return created(serving("POST", path, body));
    }
    @Test
    void importsExactReadyAssignmentAndLaterVolunteerPauseStopsAdmissions() throws Exception {
        assertThat(call("GET", "/sessions/" + session + "/serving-candidates", null).body()).contains("LEADER")
            .doesNotContain("Unknown");
        Response imported = call("POST", "/sessions/" + session + "/serving-staff",
            body("expectedVersion", 2, "assignmentId", booking, "responsibility", "LEADER", "present", true));
        assertThat(imported.status()).withFailMessage(imported.body()).isEqualTo(204);
        assertThat(call("GET", "/sessions/" + session, null).data().get("effectiveCapacity")).isEqualTo(2);
        assertThat(
            serving("PUT", "/volunteers/" + volunteer + "/status", body("expectedVersion", 1, "status", "PAUSED"))
                .status())
            .isEqualTo(204);
        assertThat(call("GET", "/sessions/" + session, null).data().get("effectiveCapacity")).isEqualTo(0);
        assertThat(arrive(child, session, UUID.randomUUID()).status()).isEqualTo(409);
    }
    @Test
    void wrongResponsibilityAndUnconfirmedPlansCannotBecomeSessionStaff() throws Exception {
        assertThat(call("POST", "/sessions/" + session + "/serving-staff",
            body("expectedVersion", 2, "assignmentId", booking, "responsibility", "ASSISTANT", "present", true))
            .status()).isEqualTo(409);
        assertThat(serving("POST", "/assignments/" + booking + "/status",
            body("expectedVersion", 0, "status", "PROPOSED", "reason", "Awaiting commitment")).status()).isEqualTo(204);
        assertThat(call("POST", "/sessions/" + session + "/serving-staff",
            body("expectedVersion", 2, "assignmentId", booking, "responsibility", "LEADER", "present", true)).status())
            .isEqualTo(409);
    }
}
