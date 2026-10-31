package org.onechurch.events.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_ATTENDANCE;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_ATTENDANCE_HISTORY;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_OCCURRENCES;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_REGISTRATIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.EventDayIntegrationTestSupport;

import java.time.OffsetDateTime;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
class EventAttendanceApiIntegrationTest extends EventDayIntegrationTestSupport {
    @Test
    void checkInIsIdempotentAndDuplicateArrivalsCannotBeInserted() throws Exception {
        UUID occurrence = nearOccurrence(2), booking = register(occurrence, person("Solly")), key = UUID.randomUUID();
        UUID attendance = created(arrive(booking, key));
        assertThat(created(arrive(booking, key))).isEqualTo(attendance);
        assertThat(arrive(booking, UUID.randomUUID()).status()).isEqualTo(409);
        assertThat(db.fetchCount(EVENT_ATTENDANCE)).isEqualTo(1);
        Response roster = request("GET", ROOT + "/occurrences/" + occurrence + "/attendance?search=Solly", admin, null);
        assertThat(roster.status()).withFailMessage(roster.body()).isEqualTo(200);
        assertThat(roster.data().get("total")).isEqualTo(1);
        assertThat(roster.body()).contains("PRESENT", "Solly Mabasa");
        assertThat(request("POST", ROOT + "/registrations/" + booking + "/cancel", admin,
            Map.of("expectedVersion", 0, "reason", "Mistaken cancellation")).status()).isEqualTo(409);
    }
    @Test
    void correctionsPreserveTheOriginalArrivalAndRequireCurrentVersions() throws Exception {
        UUID occurrence = nearOccurrence(1), booking = register(occurrence, person("Naledi"));
        created(arrive(booking, UUID.randomUUID()));
        OffsetDateTime arrival = db.selectFrom(EVENT_ATTENDANCE).fetchOne().getArrivedAt();
        Map<String, Object> voided =
            Map.of("expectedVersion", 0, "status", "VOIDED", "reason", "Wrong person selected");
        assertThat(
            request("POST", ROOT + "/registrations/" + booking + "/attendance-correction", admin, voided).status())
            .isEqualTo(204);
        assertThat(
            request("POST", ROOT + "/registrations/" + booking + "/attendance-correction", admin, voided).status())
            .isEqualTo(409);
        assertThat(request("POST", ROOT + "/registrations/" + booking + "/attendance-correction", admin,
            Map.of("expectedVersion", 1, "status", "PRESENT", "reason", "Verified original arrival")).status())
            .isEqualTo(204);
        assertThat(db.selectFrom(EVENT_ATTENDANCE).fetchOne().getArrivedAt()).isEqualTo(arrival);
        assertThat(db.fetchCount(EVENT_ATTENDANCE_HISTORY)).isEqualTo(3);
        assertThat(request("GET", ROOT + "/registrations/" + booking + "/attendance-history", admin, null).body())
            .contains("VOIDED", "REINSTATED", "Wrong person selected");
    }
    @Test
    void walkInsRespectWaitlistPriorityAndDoNotAdmitWaitlistedPeople() throws Exception {
        UUID occurrence = nearOccurrence(1);
        register(occurrence, person("Booked"));
        Response queued = walk(occurrence, person("Waiting"));
        assertThat(queued.status()).withFailMessage(queued.body()).isEqualTo(201);
        assertThat(queued.data().get("admitted")).isEqualTo(false);
        assertThat(queued.data().get("status")).isEqualTo("WAITLISTED");
        assertThat(db.fetchCount(EVENT_ATTENDANCE)).isZero();
        long version = db.selectFrom(org.onechurch.shared.infrastructure.jooq.Tables.EVENT_REGISTRATION_POLICIES)
            .fetchOne()
            .getVersion();
        policy(occurrence, version, 2);
        Response third = walk(occurrence, person("Later"));
        assertThat(third.data().get("status")).isEqualTo("WAITLISTED");
        UUID promoted = UUID.fromString((String) queued.data().get("registrationId"));
        assertThat(db.selectFrom(EVENT_REGISTRATIONS).where(EVENT_REGISTRATIONS.ID.eq(promoted)).fetchOne().getStatus())
            .isEqualTo("CONFIRMED");
        assertThat(arrive(promoted, UUID.randomUUID()).status()).isEqualTo(409);
        long promotedVersion =
            db.selectFrom(EVENT_REGISTRATIONS).where(EVENT_REGISTRATIONS.ID.eq(promoted)).fetchOne().getVersion();
        assertThat(request("POST", ROOT + "/registrations/" + promoted + "/check-in", admin,
            Map.of("requestId", UUID.randomUUID(), "expectedVersion", promotedVersion)).status()).isEqualTo(201);
    }
    @Test
    void arrivalWindowsAndCancelledOccurrencesBlockCheckIn() throws Exception {
        UUID event = event(venue, "2099-07-01", 1);
        Map<String, Object> o = dates(event).getFirst();
        UUID occurrence = UUID.fromString((String) o.get("id"));
        policy(occurrence, 0, 2);
        UUID booking = register(occurrence, person("Future"));
        assertThat(arrive(booking, UUID.randomUUID()).status()).isEqualTo(409);
        assertThat(walk(occurrence, person("Early walkin")).status()).isEqualTo(409);
        assertThat(apply(change(o, "CANCEL", "THIS", "2099-07-01", "09:00", venue)).status()).isEqualTo(204);
        assertThat(arrive(booking, UUID.randomUUID()).status()).isEqualTo(409);
    }
    @Test
    void concurrentArrivalsAndWalkInsCannotDuplicateOrOverbook() throws Exception {
        UUID occurrence = nearOccurrence(1), a = person("First"), b = person("Second");
        CompletableFuture<Response> first = CompletableFuture.supplyAsync(() -> walkUnchecked(occurrence, a)),
            second = CompletableFuture.supplyAsync(() -> walkUnchecked(occurrence, b));
        assertThat(first.join().status()).isEqualTo(201);
        assertThat(second.join().status()).isEqualTo(201);
        assertThat(db.fetchCount(EVENT_ATTENDANCE)).isEqualTo(1);
        assertThat(db.fetchCount(EVENT_REGISTRATIONS, EVENT_REGISTRATIONS.STATUS.eq("CONFIRMED"))).isEqualTo(1);
        assertThat(db.fetchCount(EVENT_REGISTRATIONS, EVENT_REGISTRATIONS.STATUS.eq("WAITLISTED"))).isEqualTo(1);
    }
    @Test
    void occurrenceCancellationPreservesHistoricalArrivals() throws Exception {
        UUID occurrence = nearOccurrence(2), booking = register(occurrence, person("Attended"));
        created(arrive(booking, UUID.randomUUID()));
        Map<String, Object> o =
            (Map<String, Object>) request("GET", ROOT + "/occurrences/" + occurrence, admin, null).data()
                .get("occurrence");
        assertThat(apply(change(o, "CANCEL", "THIS", o.get("originDate").toString(), "09:00", venue)).status())
            .isEqualTo(204);
        assertThat(db.selectFrom(EVENT_ATTENDANCE).fetchOne().getStatus()).isEqualTo("PRESENT");
        assertThat(
            request("GET", ROOT + "/occurrences/" + occurrence + "/attendance?status=PRESENT", admin, null).body())
            .contains("Attended Mabasa");
    }
    @Test
    @SuppressWarnings("unchecked")
    void arrivalsPreventMovingAttendanceOntoAnotherDate() throws Exception {
        UUID occurrence = nearOccurrence(2), booking = register(occurrence, person("Arrived early"));
        created(arrive(booking, UUID.randomUUID()));
        Map<String, Object> current =
            (Map<String, Object>) request("GET", ROOT + "/occurrences/" + occurrence, admin, null).data()
                .get("occurrence");
        Response preview = request("POST", ROOT + "/occurrences/preview", admin,
            Map.of("draft", change(current, "RESCHEDULE", "THIS", "2099-01-01", "09:00", venue)));
        assertThat(preview.status()).withFailMessage(preview.body()).isEqualTo(409);
        assertThat(preview.body()).contains("already checked in");
    }
    @Test
    void coordinatorsCanOpenRegistrationDuringTheGatheringButNotAfterItEnds() throws Exception {
        UUID occurrence = nearOccurrence(1);
        Map<String, Object> closed = Map.of("expectedVersion", 1, "open", false, "capacity", 1, "waitlistEnabled", true,
            "reason", "Pause arrivals");
        assertThat(request("PUT", ROOT + "/occurrences/" + occurrence + "/registration", admin, closed).status())
            .isEqualTo(204);
        assertThat(walk(occurrence, person("Closed booking")).status()).isEqualTo(409);
        db.update(EVENT_OCCURRENCES)
            .set(EVENT_OCCURRENCES.STARTS_AT, OffsetDateTime.now().minusMinutes(30))
            .where(EVENT_OCCURRENCES.ID.eq(occurrence))
            .execute();
        policy(occurrence, 2, 1);
        Response admitted = walk(occurrence, person("During gathering"));
        assertThat(admitted.status()).withFailMessage(admitted.body()).isEqualTo(201);
        assertThat(admitted.data().get("admitted")).isEqualTo(true);
        db.update(EVENT_OCCURRENCES)
            .set(EVENT_OCCURRENCES.ENDS_AT, OffsetDateTime.now().minusMinutes(1))
            .where(EVENT_OCCURRENCES.ID.eq(occurrence))
            .execute();
        assertThat(walk(occurrence, person("After end")).status()).isEqualTo(409);
    }
    private Response walkUnchecked(UUID occurrence, UUID person) {
        try {
            return walk(occurrence, person);
        } catch (Exception e) {
            throw new IllegalStateException(e);
        }
    }
}
