package org.onechurch.shared.infrastructure;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_DEFINITIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_VENUES;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.BeforeEach;

import java.util.List;
import java.util.Map;
import java.util.UUID;
public abstract class EventsIntegrationTestSupport extends PeopleIntegrationTestSupport {
    protected static final String ROOT = "/api/events";
    protected UUID venue;
    @BeforeEach
    protected void resetEvents() throws Exception {
        db.truncate(EVENT_DEFINITIONS).cascade().execute();
        db.truncate(EVENT_VENUES).cascade().execute();
        for (String code : List.of("events.read", "events.manage", "events.registrations.read",
            "events.registrations.manage")) {
            UUID id = UUID.randomUUID();
            db.insertInto(PERMISSIONS).set(PERMISSIONS.ID, id).set(PERMISSIONS.CODE, code).execute();
            db.insertInto(ROLE_PERMISSIONS)
                .set(ROLE_PERMISSIONS.ROLE_ID, ADMIN_ROLE)
                .set(ROLE_PERMISSIONS.PERMISSION_ID, id)
                .execute();
        }
        venue = created(request("POST", ROOT + "/venues", admin,
            Map.of("name", "Main hall", "address", "10 Church Road", "exclusive", true)));
    }
    protected Map<String, Object> schedule(UUID at, String date, int count) {
        return Map.of("venueId", at, "timeZone", "Africa/Johannesburg", "frequency", count == 1 ? "ONCE" : "WEEKLY",
            "interval", 1, "count", count, "firstDate", date, "startsAt", "09:00", "endsAt", "10:30", "endDayOffset",
            0);
    }
    protected UUID event(UUID at, String date, int count) throws Exception {
        Map<String, Object> draft = schedule(at, date, count);
        Response preview = request("POST", ROOT + "/schedule/preview", admin, Map.of("draft", draft));
        assertThat(preview.status()).withFailMessage(preview.body()).isEqualTo(200);
        return created(request("POST", ROOT, admin,
            Map.of("requestId", UUID.randomUUID(), "title", "Sunday gathering", "type", "SERVICE", "description",
                "Welcome together", "schedule", draft, "previewHash", preview.data().get("hash"))));
    }
    @SuppressWarnings("unchecked")
    protected List<Map<String, Object>> dates(UUID event) throws Exception {
        Response response = request("GET",
            ROOT + "/calendar?eventId=" + event + "&from=2099-01-01T00:00:00Z&until=2100-01-01T00:00:00Z&limit=100",
            admin, null);
        assertThat(response.status()).withFailMessage(response.body()).isEqualTo(200);
        return ((List<Map<String, Object>>) response.data().get("occurrences")).stream()
            .map(r -> (Map<String, Object>) r.get("occurrence"))
            .toList();
    }
    protected Map<String, Object> change(Map<String, Object> occurrence, String action, String scope, String date,
        String time, UUID at) {
        return Map.of("occurrenceId", occurrence.get("id"), "expectedVersion", occurrence.get("version"), "action",
            action, "scope", scope, "date", date, "startsAt", time, "endsAt", "12:00", "endDayOffset", 0, "venueId",
            at);
    }
    protected Response apply(Map<String, Object> draft) throws Exception {
        Response preview = request("POST", ROOT + "/occurrences/preview", admin, Map.of("draft", draft));
        assertThat(preview.status()).withFailMessage(preview.body()).isEqualTo(200);
        return request("POST",
            ROOT + "/occurrences/" + draft.get("action").toString().toLowerCase(java.util.Locale.ROOT), admin,
            Map.of("draft", draft, "reviewHash", preview.data().get("hash"), "reason",
                "Coordinator reviewed the gathering."));
    }
    protected void policy(UUID occurrence, long version, int capacity) throws Exception {
        Response response =
            request("PUT", ROOT + "/occurrences/" + occurrence + "/registration", admin, Map.of("expectedVersion",
                version, "open", true, "capacity", capacity, "waitlistEnabled", true, "reason", "Reviewed capacity"));
        assertThat(response.status()).withFailMessage(response.body()).isEqualTo(204);
    }
    protected UUID register(UUID occurrence, UUID person) throws Exception {
        return created(request("POST", ROOT + "/occurrences/" + occurrence + "/registrations", admin,
            Map.of("requestId", UUID.randomUUID(), "personId", person)));
    }
}
