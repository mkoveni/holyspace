package org.onechurch.shared.infrastructure;
import static org.assertj.core.api.Assertions.assertThat;
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
public abstract class EventDayIntegrationTestSupport extends EventsIntegrationTestSupport {
    @BeforeEach
    protected void eventDayPermissions() {
        db.truncate(org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_EMAIL_DELIVERIES).cascade().execute();
        for (String code : List.of("events.attendance.read", "events.attendance.manage",
            "events.communications.send")) {
            UUID id = UUID.randomUUID();
            db.insertInto(PERMISSIONS).set(PERMISSIONS.ID, id).set(PERMISSIONS.CODE, code).execute();
            db.insertInto(ROLE_PERMISSIONS)
                .set(ROLE_PERMISSIONS.ROLE_ID, ADMIN_ROLE)
                .set(ROLE_PERMISSIONS.PERMISSION_ID, id)
                .execute();
        }
    }
    protected UUID nearOccurrence(int capacity) throws Exception {
        ZonedDateTime start = Instant.now().plusSeconds(900).truncatedTo(ChronoUnit.MINUTES).atZone(ZoneOffset.UTC),
            end = start.plusHours(1);
        Map<String, Object> draft = Map.of("venueId", venue, "timeZone", "UTC", "frequency", "ONCE", "interval", 1,
            "count", 1, "firstDate", start.toLocalDate().toString(), "startsAt", start.toLocalTime().toString(),
            "endsAt", end.toLocalTime().toString(), "endDayOffset",
            (int) java.time.temporal.ChronoUnit.DAYS.between(start.toLocalDate(), end.toLocalDate()));
        Response preview = request("POST", ROOT + "/schedule/preview", admin, Map.of("draft", draft));
        assertThat(preview.status()).withFailMessage(preview.body()).isEqualTo(200);
        UUID event =
            created(request("POST", ROOT, admin, Map.of("requestId", UUID.randomUUID(), "title", "Welcome gathering",
                "type", "MEETING", "description", "", "schedule", draft, "previewHash", preview.data().get("hash"))));
        Response dates = request("GET", ROOT + "/calendar?eventId=" + event + "&from=" + start.minusDays(1).toInstant()
            + "&until=" + start.plusDays(1).toInstant(), admin, null);
        Map<?, ?> row = (Map<?, ?>) ((List<?>) dates.data().get("occurrences")).getFirst();
        UUID id = UUID.fromString((String) ((Map<?, ?>) row.get("occurrence")).get("id"));
        policy(id, 0, capacity);
        return id;
    }
    protected Response arrive(UUID registration, UUID requestId) throws Exception {
        return request("POST", ROOT + "/registrations/" + registration + "/check-in", admin,
            Map.of("requestId", requestId, "expectedVersion", 0));
    }
    protected Response walk(UUID occurrence, UUID person) throws Exception {
        return request("POST", ROOT + "/occurrences/" + occurrence + "/walk-ins", admin,
            Map.of("requestId", UUID.randomUUID(), "personId", person));
    }
}
