package org.onechurch.shared.infrastructure;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.BeforeEach;

import java.time.Instant;
import java.time.ZoneOffset;
import java.time.ZonedDateTime;
import java.time.temporal.ChronoUnit;
import java.util.List;
import java.util.Map;
import java.util.UUID;
public abstract class CommunityIntegrationTestSupport extends EventDayIntegrationTestSupport {
    protected static final String COMMUNITIES_ROOT = "/api/communities";
    @BeforeEach
    protected void resetCommunities() {
        db.truncate(COMMUNITIES).cascade().execute();
        db.truncate(org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_REQUESTS).execute();
        for (String code : List.of("ministries.read", "ministries.manage", "ministries.lead",
            "ministries.communications.send")) {
            UUID permission = UUID.randomUUID();
            db.insertInto(PERMISSIONS).set(PERMISSIONS.ID, permission).set(PERMISSIONS.CODE, code).execute();
            db.insertInto(ROLE_PERMISSIONS)
                .set(ROLE_PERMISSIONS.ROLE_ID, ADMIN_ROLE)
                .set(ROLE_PERMISSIONS.PERMISSION_ID, permission)
                .execute();
        }
    }
    protected UUID community(String kind, String name) throws Exception {
        return created(request("POST", COMMUNITIES_ROOT, admin, Map.of("requestId", UUID.randomUUID(), "kind", kind,
            "name", name, "description", "Shared purpose", "campus", "Central")));
    }
    protected UUID join(UUID community, UUID person) throws Exception {
        return created(request("POST", COMMUNITIES_ROOT + "/" + community + "/members", admin,
            Map.of("requestId", UUID.randomUUID(), "personId", person)));
    }
    protected UUID groupMeeting(UUID community) throws Exception {
        ZonedDateTime start = Instant.now().plusSeconds(900).truncatedTo(ChronoUnit.MINUTES).atZone(ZoneOffset.UTC),
            end = start.plusHours(1);
        Map<String, Object> schedule = Map.of("venueId", venue, "timeZone", "UTC", "frequency", "ONCE", "interval", 1,
            "count", 1, "firstDate", start.toLocalDate().toString(), "startsAt", start.toLocalTime().toString(),
            "endsAt", end.toLocalTime().toString(), "endDayOffset",
            (int) ChronoUnit.DAYS.between(start.toLocalDate(), end.toLocalDate()));
        Response preview = request("POST", COMMUNITIES_ROOT + "/" + community + "/meeting-preview", admin,
            Map.of("schedule", schedule));
        assertThat(preview.status()).withFailMessage(preview.body()).isEqualTo(200);
        return created(request("POST", COMMUNITIES_ROOT + "/" + community + "/meetings", admin, Map.of("requestId",
            UUID.randomUUID(), "expectedVersion", 0, "schedule", schedule, "reviewHash", preview.data().get("hash"))));
    }
    protected Response prepare(UUID meeting, long version) throws Exception {
        return request("POST", COMMUNITIES_ROOT + "/meetings/" + meeting + "/prepare", admin,
            Map.of("expectedVersion", version));
    }
    protected UUID rosterRow(UUID meeting, UUID person) {
        return db.select(org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_ROSTER.ID)
            .from(org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_ROSTER)
            .where(org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_ROSTER.MEETING_ID.eq(meeting))
            .and(org.onechurch.shared.infrastructure.jooq.Tables.COMMUNITY_ROSTER.PERSON_ID.eq(person))
            .fetchOne(0, UUID.class);
    }
    protected Response mark(UUID row, long version, String status) throws Exception {
        return request("PUT", COMMUNITIES_ROOT + "/roster/" + row, admin,
            Map.of("expectedVersion", version, "status", status, "reason", "Verified by group coordinator"));
    }
}
