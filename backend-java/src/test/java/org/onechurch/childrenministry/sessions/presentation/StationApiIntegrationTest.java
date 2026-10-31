package org.onechurch.childrenministry.sessions.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;

import java.time.Instant;
import java.util.UUID;
class StationApiIntegrationTest extends ChildrenIntegrationTestSupport {
    @Test
    void assignedOperatorsDiscoverTheirSessionsAndReadNamesWithoutDirectoryPermission() throws Exception {
        arrival();
        UUID otherClass = create("/classes",
            body("programmeId", programme, "name", "Other class", "minAgeMonths", 0, "maxAgeMonths", 216));
        UUID otherRoom = create("/rooms", body("locationId", location, "name", "Other room", "capacity", 10));
        UUID other = create("/sessions", body("classId", otherClass, "roomId", otherRoom, "startsAt",
            Instant.now().minusSeconds(600), "endsAt", end, "seatLimit", 2));
        UUID stationPermission = db.select(PERMISSIONS.ID)
            .from(PERMISSIONS)
            .where(PERMISSIONS.CODE.eq("children.station"))
            .fetchOne(PERMISSIONS.ID);
        db.deleteFrom(ROLE_PERMISSIONS)
            .where(ROLE_PERMISSIONS.ROLE_ID.eq(ADMIN_ROLE))
            .and(ROLE_PERMISSIONS.PERMISSION_ID.ne(stationPermission))
            .execute();
        Response sessions = call("GET", "/station/sessions?limit=1", null);
        assertThat(sessions.status()).isEqualTo(200);
        assertThat(sessions.body()).contains(session.toString()).doesNotContain(other.toString());
        assertThat(call("GET", "/station/sessions/" + session, null).status()).isEqualTo(200);
        assertThat(call("GET", "/station/sessions/" + other, null).status()).isEqualTo(403);
        assertThat(call("GET", "/sessions/" + session, null).status()).isEqualTo(403);
        Response roster = call("GET", "/station/sessions/" + session + "/attendance", null);
        assertThat(roster.status()).isEqualTo(200);
        assertThat(roster.body()).contains("Child Mabasa").doesNotContain("dateOfBirth", "labelHash", "allergies");
        assertThat(call("GET", "/station/sessions/" + other + "/attendance", null).status()).isEqualTo(403);
    }
    @Test
    void supervisorsDiscoverAllSessionsAndSearchWindowsRemainBounded() throws Exception {
        assertThat(call("GET", "/station/sessions", null).status()).isEqualTo(200);
        assertThat(call("GET", "/station/sessions?from=2026-01-01T00:00:00Z&until=2026-03-01T00:00:00Z", null).status())
            .isEqualTo(400);
        assertThat(call("GET", "/station/sessions?limit=101", null).status()).isEqualTo(400);
    }
}
