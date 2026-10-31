package org.onechurch.events.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_DEFINITIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_OCCURRENCE_HISTORY;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_REGISTRATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_REGISTRATION_HISTORY;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_REGISTRATION_POLICIES;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.EventsIntegrationTestSupport;

import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
class EventsApiIntegrationTest extends EventsIntegrationTestSupport {
    @Test
    void finiteSeriesIsIdempotentAndCalendarIsBounded() throws Exception {
        Map<String, Object> draft = schedule(venue, "2099-01-04", 4);
        Response preview = request("POST", ROOT + "/schedule/preview", admin, Map.of("draft", draft));
        Map<String, Object> body = Map.of("requestId", UUID.randomUUID(), "title", "Sunday service", "type", "SERVICE",
            "description", "Welcome", "schedule", draft, "previewHash", preview.data().get("hash"));
        UUID id = created(request("POST", ROOT, admin, body));
        assertThat(created(request("POST", ROOT, admin, body))).isEqualTo(id);
        assertThat(dates(id)).hasSize(4);
        assertThat(db.fetchCount(EVENT_DEFINITIONS)).isEqualTo(1);
        assertThat(request("GET", ROOT + "/calendar?from=2099-01-01T00:00:00Z&until=2101-01-01T00:00:00Z", admin, null)
            .status()).isEqualTo(400);
        assertThat(request("GET", ROOT + "?limit=101", admin, null).status()).isEqualTo(400);
        assertThat(request("GET", ROOT + "?search=Sunday&limit=1", admin, null).data().get("total")).isEqualTo(1);
    }
    @Test
    void staleMetadataAndScheduleReviewsRollback() throws Exception {
        Map<String, Object> draft = schedule(venue, "2099-01-04", 1);
        String hash =
            (String) request("POST", ROOT + "/schedule/preview", admin, Map.of("draft", draft)).data().get("hash");
        assertThat(request("PUT", ROOT + "/venues/" + venue, admin,
            Map.of("expectedVersion", 0, "name", "Renamed hall", "address", "New address", "active", true)).status())
            .isEqualTo(204);
        assertThat(request("POST", ROOT, admin, Map.of("requestId", UUID.randomUUID(), "title", "Test", "type",
            "SERVICE", "description", "", "schedule", draft, "previewHash", hash)).status()).isEqualTo(409);
        assertThat(db.fetchCount(EVENT_DEFINITIONS)).isZero();
        UUID id = event(venue, "2099-01-04", 1);
        Map<String, Object> edit = Map.of("expectedVersion", 0, "title", "Changed", "type", "SERVICE", "description",
            "", "active", true, "reason", "Correct title");
        assertThat(request("PUT", ROOT + "/" + id, admin, edit).status()).isEqualTo(204);
        assertThat(request("PUT", ROOT + "/" + id, admin, edit).status()).isEqualTo(409);
        assertThat(request("PUT", ROOT + "/" + id, admin, Map.of("expectedVersion", 1, "title", "Changed", "type",
            "SERVICE", "description", "", "active", false, "reason", "Archive")).status()).isEqualTo(409);
    }
    @Test
    void overridesKeepTheirIdentityAndFutureChangesPreserveThem() throws Exception {
        UUID id = event(venue, "2099-01-04", 4);
        List<Map<String, Object>> original = dates(id);
        Map<String, Object> second = original.get(1);
        assertThat(apply(change(second, "RESCHEDULE", "THIS", "2099-01-12", "10:00", venue)).status()).isEqualTo(204);
        assertThat(apply(change(original.getFirst(), "RESCHEDULE", "FUTURE", "2099-01-04", "11:00", venue)).status())
            .isEqualTo(204);
        List<Map<String, Object>> after = dates(id);
        assertThat(after.stream().map(o -> o.get("id")))
            .containsExactlyElementsOf(original.stream().map(o -> o.get("id")).toList());
        assertThat(after.get(1).get("startsAt")).isEqualTo("2099-01-12T08:00:00Z");
        assertThat(after.get(1).get("overridden")).isEqualTo(true);
        assertThat(after.getFirst().get("startsAt")).isEqualTo("2099-01-04T09:00:00Z");
        assertThat(request("GET", ROOT + "/occurrences/" + second.get("id") + "/history", admin, null).body())
            .contains("RESCHEDULE");
        assertThat(apply(change(after.getFirst(), "CANCEL", "FUTURE", "2099-01-04", "11:00", venue)).status())
            .isEqualTo(204);
        assertThat(dates(id)).allSatisfy(o -> assertThat(o.get("status")).isEqualTo("CANCELLED"));
        Map<String, Object> cancelled = dates(id).get(1);
        assertThat(apply(change(cancelled, "RESTORE", "THIS", "2099-01-12", "10:00", venue)).status()).isEqualTo(204);
        assertThat(dates(id).get(1).get("id")).isEqualTo(second.get("id"));
        assertThat(dates(id).get(1).get("status")).isEqualTo("SCHEDULED");
    }
    @Test
    void registrationsAreFifoAndCancellationAndRestorationAreExplicit() throws Exception {
        UUID event = event(venue, "2099-02-01", 1);
        UUID occurrence = UUID.fromString((String) dates(event).getFirst().get("id"));
        policy(occurrence, 0, 1);
        UUID first = register(occurrence, person("First")), second = register(occurrence, person("Second")),
            third = register(occurrence, person("Third"));
        assertThat(db.selectFrom(EVENT_REGISTRATIONS).where(EVENT_REGISTRATIONS.ID.eq(second)).fetchOne().getStatus())
            .isEqualTo("WAITLISTED");
        assertThat(request("POST", ROOT + "/registrations/" + first + "/cancel", admin,
            Map.of("expectedVersion", 0, "reason", "Cannot attend")).status()).isEqualTo(204);
        assertThat(db.selectFrom(EVENT_REGISTRATIONS).where(EVENT_REGISTRATIONS.ID.eq(second)).fetchOne().getStatus())
            .isEqualTo("CONFIRMED");
        assertThat(db.selectFrom(EVENT_REGISTRATIONS).where(EVENT_REGISTRATIONS.ID.eq(third)).fetchOne().getStatus())
            .isEqualTo("WAITLISTED");
        assertThat(apply(change(dates(event).getFirst(), "CANCEL", "THIS", "2099-02-01", "09:00", venue)).status())
            .isEqualTo(204);
        assertThat(db.select(EVENT_REGISTRATIONS.STATUS).from(EVENT_REGISTRATIONS).fetch(EVENT_REGISTRATIONS.STATUS))
            .containsOnly("CANCELLED");
        assertThat(db.fetchCount(EVENT_REGISTRATION_HISTORY)).isEqualTo(7);
        assertThat(apply(change(dates(event).getFirst(), "RESTORE", "THIS", "2099-02-01", "09:00", venue)).status())
            .isEqualTo(204);
        assertThat(db.selectFrom(EVENT_REGISTRATION_POLICIES).fetchOne().getOpen()).isFalse();
        assertThat(db.select(EVENT_REGISTRATIONS.STATUS).from(EVENT_REGISTRATIONS).fetch(EVENT_REGISTRATIONS.STATUS))
            .containsOnly("CANCELLED");
        assertThat(request("GET", ROOT + "/occurrences/" + occurrence + "/registrations", admin, null).body())
            .contains("First Mabasa", "Second Mabasa", "Third Mabasa");
    }
    @Test
    void registrationChangesInvalidateACancellationReview() throws Exception {
        UUID event = event(venue, "2099-03-01", 1);
        Map<String, Object> occurrence = dates(event).getFirst();
        UUID occurrenceId = UUID.fromString((String) occurrence.get("id"));
        policy(occurrenceId, 0, 2);
        Map<String, Object> draft = change(occurrence, "CANCEL", "THIS", "2099-03-01", "09:00", venue);
        String hash =
            (String) request("POST", ROOT + "/occurrences/preview", admin, Map.of("draft", draft)).data().get("hash");
        register(occurrenceId, person("Added after review"));
        assertThat(request("POST", ROOT + "/occurrences/cancel", admin,
            Map.of("draft", draft, "reviewHash", hash, "reason", "Old review")).status()).isEqualTo(409);
        assertThat(dates(event).getFirst().get("status")).isEqualTo("SCHEDULED");
        assertThat(db.fetchCount(EVENT_OCCURRENCE_HISTORY)).isEqualTo(1);
    }
    @Test
    void venueConflictsAreAtomicAndAdjacentDatesCoexist() throws Exception {
        event(venue, "2099-04-01", 1);
        assertThat(request("POST", ROOT + "/schedule/preview", admin, Map.of("draft", schedule(venue, "2099-04-01", 1)))
            .status()).isEqualTo(409);
        Map<String, Object> adjacent = new HashMap<>(schedule(venue, "2099-04-01", 1));
        adjacent.put("startsAt", "10:30");
        adjacent.put("endsAt", "12:00");
        assertThat(request("POST", ROOT + "/schedule/preview", admin, Map.of("draft", adjacent)).status())
            .isEqualTo(200);
        Map<String, Object> dst = new HashMap<>(schedule(venue, "2099-03-08", 1));
        dst.put("timeZone", "America/New_York");
        dst.put("firstDate", "2027-03-14");
        dst.put("startsAt", "02:30");
        dst.put("endsAt", "04:00");
        assertThat(request("POST", ROOT + "/schedule/preview", admin, Map.of("draft", dst)).status()).isEqualTo(400);
    }
    @Test
    void concurrentRegistrationsCannotOverbookTheLastSeat() throws Exception {
        UUID event = event(venue, "2099-05-01", 1);
        UUID occurrence = UUID.fromString((String) dates(event).getFirst().get("id"));
        policy(occurrence, 0, 1);
        UUID a = person("A"), b = person("B");
        CompletableFuture<UUID> first = CompletableFuture.supplyAsync(() -> registerUnchecked(occurrence, a));
        CompletableFuture<UUID> second = CompletableFuture.supplyAsync(() -> registerUnchecked(occurrence, b));
        first.join();
        second.join();
        assertThat(db.fetchCount(EVENT_REGISTRATIONS, EVENT_REGISTRATIONS.STATUS.eq("CONFIRMED"))).isEqualTo(1);
        assertThat(db.fetchCount(EVENT_REGISTRATIONS, EVENT_REGISTRATIONS.STATUS.eq("WAITLISTED"))).isEqualTo(1);
    }
    private UUID registerUnchecked(UUID occurrence, UUID person) {
        try {
            return register(occurrence, person);
        } catch (Exception e) {
            throw new IllegalStateException(e);
        }
    }
    @Test
    @SuppressWarnings("unchecked")
    void documentationKeepsNestedDraftSchemasDistinct() throws Exception {
        Response docs = request("GET", "/v3/api-docs", null, null);
        Map<String, Object> schemas =
            (Map<String, Object>) ((Map<String, Object>) docs.data().get("components")).get("schemas");
        Map<String, Object> scheduleRequest = (Map<String, Object>) schemas.get("PreviewEventScheduleRequest");
        Map<String, Object> changeRequest = (Map<String, Object>) schemas.get("PreviewEventOccurrenceChangeRequest");
        String scheduleRef =
            (String) ((Map<String, Object>) ((Map<String, Object>) scheduleRequest.get("properties")).get("draft"))
                .get("$ref");
        String changeRef =
            (String) ((Map<String, Object>) ((Map<String, Object>) changeRequest.get("properties")).get("draft"))
                .get("$ref");
        assertThat(scheduleRef).isNotEqualTo(changeRef);
        assertThat(schemas.get(scheduleRef.substring(scheduleRef.lastIndexOf('/') + 1))).asString()
            .contains("frequency", "firstDate", "venueId");
        assertThat(schemas.get(changeRef.substring(changeRef.lastIndexOf('/') + 1))).asString()
            .contains("occurrenceId", "action", "scope");
    }
    @Test
    void permissionsProtectHttpAndDocumentationIncludesNewOperations() throws Exception {
        assertThat(request("GET", ROOT, null, null).status()).isEqualTo(401);
        String reader = readerToken();
        assertThat(request("GET", ROOT, reader, null).status()).isEqualTo(403);
        assertThat(
            request("POST", ROOT + "/venues", reader, Map.of("name", "Blocked", "address", "", "exclusive", false))
                .status())
            .isEqualTo(403);
        Response docs = request("GET", "/v3/api-docs", null, null);
        assertThat(docs.status()).isEqualTo(200);
        assertThat(docs.body()).contains("/api/events/occurrences/reschedule", "/api/events/schedule/preview",
            "/api/events/occurrences/{id}/registrations");
    }
}
