package org.onechurch.events.infrastructure;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_ASSIGNMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_TEAMS;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_VOLUNTEERS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.EventsIntegrationTestSupport;

import java.util.List;
import java.util.Map;
import java.util.UUID;
class EventServingActivitiesIntegrationTest extends EventsIntegrationTestSupport {
    @Test
    void venueOnlyChangesInvalidateReadinessAndRequireRenewedCommitment() throws Exception {
        db.truncate(SERVING_TEAMS).cascade().execute();
        db.truncate(SERVING_VOLUNTEERS).cascade().execute();
        for (String code : List.of("volunteering.read", "volunteering.manage")) {
            UUID id = UUID.randomUUID();
            db.insertInto(PERMISSIONS).set(PERMISSIONS.ID, id).set(PERMISSIONS.CODE, code).execute();
            db.insertInto(ROLE_PERMISSIONS)
                .set(ROLE_PERMISSIONS.ROLE_ID, ADMIN_ROLE)
                .set(ROLE_PERMISSIONS.PERMISSION_ID, id)
                .execute();
        }
        UUID event = event(venue, "2099-06-01", 1);
        Map<String, Object> occurrence = dates(event).getFirst();
        UUID occurrenceId = UUID.fromString((String) occurrence.get("id"));
        UUID team = created(request("POST", "/api/volunteering/teams", admin, Map.of("name", "Welcome team")));
        UUID role = created(request("POST", "/api/volunteering/teams/" + team + "/roles", admin,
            Map.of("expectedVersion", 0, "name", "Host", "childrenRole", "NONE", "requiredCredentials", List.of())));
        UUID person = person("Solly");
        UUID volunteer = created(request("POST", "/api/volunteering/volunteers", admin, Map.of("personId", person)));
        java.util.Map<String, Object> invalid = new java.util.HashMap<>(
            Map.of("requestId", UUID.randomUUID(), "teamId", team, "roleId", role, "sourceId", occurrenceId, "startsAt",
                occurrence.get("startsAt"), "endsAt", occurrence.get("endsAt"), "positions", 2));
        invalid.put("sourceKind", null);
        assertThat(request("POST", "/api/volunteering/shifts", admin, invalid).status()).isEqualTo(400);
        UUID shift = created(request("POST", "/api/volunteering/shifts", admin,
            Map.of("requestId", UUID.randomUUID(), "teamId", team, "roleId", role, "sourceKind", "EVENT_OCCURRENCE",
                "sourceId", occurrenceId, "startsAt", occurrence.get("startsAt"), "endsAt", occurrence.get("endsAt"),
                "positions", 2)));
        UUID assignment =
            created(request("POST", "/api/volunteering/assignments", admin, Map.of("requestId", UUID.randomUUID(),
                "shiftId", shift, "expectedVersion", 0, "volunteerId", volunteer, "status", "CONFIRMED")));
        UUID secondVenue = created(request("POST", ROOT + "/venues", admin,
            Map.of("name", "Garden", "address", "Outdoor garden", "exclusive", true)));
        Map<String, Object> move =
            new java.util.HashMap<>(change(occurrence, "RESCHEDULE", "THIS", "2099-06-01", "09:00", secondVenue));
        move.put("endsAt", "10:30");
        assertThat(apply(move).status()).isEqualTo(204);
        Response readiness =
            request("GET", "/api/volunteering/readiness?volunteerId=" + volunteer + "&shiftId=" + shift, admin, null);
        assertThat(readiness.body()).contains("rescheduled");
        assertThat(request("POST", "/api/volunteering/assignments/" + assignment + "/invitations", admin,
            Map.of("expectedVersion", 0, "expiresAt", "2099-05-31T00:00:00Z")).status()).isEqualTo(409);
        assertThat(request("PUT", "/api/volunteering/shifts/" + shift, admin, Map.of("expectedVersion", 1, "startsAt",
            occurrence.get("startsAt"), "endsAt", occurrence.get("endsAt"), "positions", 2, "status", "OPEN")).status())
            .isEqualTo(204);
        assertThat(
            db.selectFrom(SERVING_ASSIGNMENTS).where(SERVING_ASSIGNMENTS.ID.eq(assignment)).fetchOne().getStatus())
            .isEqualTo("PROPOSED");
        Map<String, Object> current = dates(event).getFirst();
        assertThat(apply(change(current, "CANCEL", "THIS", "2099-06-01", "09:00", secondVenue)).status())
            .isEqualTo(204);
        assertThat(request("POST", "/api/volunteering/assignments/" + assignment + "/status", admin,
            Map.of("expectedVersion", 1, "status", "CONFIRMED", "reason", "Accept")).status()).isEqualTo(409);
        assertThat(request("GET",
            "/api/volunteering/activities?kind=EVENT_OCCURRENCE&from=2099-06-01T00:00:00Z&until=2099-06-03T00:00:00Z&limit=1&offset=0",
            admin, null).body()).isEqualTo("[]");
    }
}
