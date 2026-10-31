package org.onechurch.shared.infrastructure;

import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_CREDENTIAL_TYPES;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_TEAMS;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_VOLUNTEERS;

import org.junit.jupiter.api.BeforeEach;

import java.time.Instant;
import java.time.temporal.ChronoUnit;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.UUID;

public abstract class VolunteerIntegrationTestSupport extends PeopleIntegrationTestSupport {
    protected UUID volunteer, team, role, shift, type;
    protected Instant start, end, validFrom, validUntil;
    @BeforeEach
    void resetServing() throws Exception {
        db.truncate(SERVING_TEAMS).cascade().execute();
        db.truncate(SERVING_VOLUNTEERS).cascade().execute();
        db.truncate(SERVING_CREDENTIAL_TYPES).cascade().execute();
        for (String code : List.of("volunteering.read", "volunteering.manage", "volunteering.credentials.manage",
            "volunteering.serve", "volunteering.communications.send")) {
            UUID id = UUID.randomUUID();
            db.insertInto(PERMISSIONS).set(PERMISSIONS.ID, id).set(PERMISSIONS.CODE, code).execute();
            db.insertInto(ROLE_PERMISSIONS)
                .set(ROLE_PERMISSIONS.ROLE_ID, ADMIN_ROLE)
                .set(ROLE_PERMISSIONS.PERMISSION_ID, id)
                .execute();
        }
        start = Instant.now().plusSeconds(86400).truncatedTo(ChronoUnit.MICROS);
        end = start.plusSeconds(3600);
        validFrom = start.minusSeconds(86400);
        validUntil = end.plusSeconds(86400);
        UUID person = person("Solly");
        volunteer = add("/volunteers", body("personId", person, "accountId", adminId));
        team = add("/teams", body("name", "Welcome team"));
        type = add("/credential-types", body("code", "screening", "name", "Screening"));
        role = add("/teams/" + team + "/roles", body("expectedVersion", 0, "name", "Welcomer", "childrenRole", "NONE",
            "requiredCredentials", List.of(type)));
        shift = add("/shifts", draft(UUID.randomUUID(), start, end, 2));
    }
    protected Response serving(String method, String path, Object body) throws Exception {
        return request(method, "/api/volunteering" + path, admin, body);
    }
    protected UUID add(String path, Object body) throws Exception {
        return created(serving("POST", path, body));
    }
    protected Map<String, Object> draft(UUID key, Instant from, Instant until, int positions) {
        return body("requestId", key, "teamId", team, "roleId", role, "sourceKind", "STANDALONE", "sourceId", null,
            "startsAt", from, "endsAt", until, "positions", positions);
    }
    protected long shiftVersion(UUID id) throws Exception {
        return ((Number) ((Map<?, ?>) serving("GET", "/shifts/" + id, null).data().get("shift")).get("version"))
            .longValue();
    }
    protected long teamVersion() throws Exception {
        return ((Number) ((Map<?, ?>) serving("GET", "/teams/" + team, null).data().get("team")).get("version"))
            .longValue();
    }
    protected long volunteerVersion() throws Exception {
        return ((Number) ((Map<?, ?>) ((Map<?, ?>) serving("GET", "/volunteers/" + volunteer, null).data()
            .get("person")).get("volunteer")).get("version")).longValue();
    }
    protected void ready() throws Exception {
        add("/teams/" + team + "/memberships", body("expectedVersion", teamVersion(), "volunteerId", volunteer,
            "validFrom", validFrom, "validUntil", validUntil));
        add("/teams/" + team + "/approvals", body("expectedVersion", teamVersion(), "volunteerId", volunteer, "roleId",
            role, "validFrom", validFrom, "validUntil", validUntil));
        add("/volunteers/" + volunteer + "/credentials", body("expectedVersion", volunteerVersion(), "typeId", type,
            "validFrom", validFrom, "validUntil", validUntil, "evidence", "Verified source / 2026"));
        assertThat(serving("PUT", "/volunteers/" + volunteer + "/availability",
            body("expectedVersion", volunteerVersion(), "weekly", List.of(), "exceptions",
                List.of(body("startsAt", validFrom, "endsAt", validUntil, "available", true))))
            .status()).isEqualTo(204);
    }
    protected UUID book(UUID volunteerId, String status) throws Exception {
        return add("/assignments", body("requestId", UUID.randomUUID(), "shiftId", shift, "expectedVersion",
            shiftVersion(shift), "volunteerId", volunteerId, "status", status));
    }
    protected static Map<String, Object> body(Object... values) {
        Map<String, Object> result = new LinkedHashMap<>();
        for (int i = 0; i < values.length; i += 2) {
            result.put((String) values[i], values[i + 1]);
        }
        return result;
    }
}
