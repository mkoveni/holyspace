package org.onechurch.events.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_ADMISSION_PASSES;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_ATTENDANCE;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_OCCURRENCES;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_REGISTRATIONS;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.onechurch.shared.infrastructure.EventDayIntegrationTestSupport;

import java.time.OffsetDateTime;
import java.util.Map;
import java.util.UUID;
class MemberEventsApiIntegrationTest extends EventDayIntegrationTestSupport {
    private UUID account, person;
    private String member;
    @BeforeEach
    void member() throws Exception {
        account = createUser("member", "member@example.org", "Member");
        member = login("member", PASSWORD);
        person = person("Member");
        link(account, person, true, 0);
    }
    private void link(UUID user, UUID who, boolean active, long version) throws Exception {
        assertThat(request("PUT", "/api/member-access/" + user, admin,
            Map.of("personId", who, "active", active, "expectedVersion", version, "evidence", "Verified in person"))
            .status()).isEqualTo(204);
    }
    private UUID eventId(UUID occurrence) {
        return db.selectFrom(EVENT_OCCURRENCES).where(EVENT_OCCURRENCES.ID.eq(occurrence)).fetchOne().getEventId();
    }
    private void publish(UUID occurrence, boolean enabled, long version) throws Exception {
        assertThat(request("PUT", ROOT + "/" + eventId(occurrence) + "/member-offer", admin,
            Map.of("enabled", enabled, "expectedVersion", version)).status()).isEqualTo(204);
    }
    private Response book(UUID occurrence, String token, UUID key) throws Exception {
        return request("POST", "/api/member/events/" + occurrence + "/registration", token,
            Map.of("requestId", key, "personId", UUID.randomUUID()));
    }
    private String pass(UUID occurrence) throws Exception {
        Response response = request("POST", "/api/member/events/" + occurrence + "/attendance-pass", member, null);
        assertThat(response.status()).isEqualTo(200);
        return (String) response.data().get("credential");
    }
    private Response scan(UUID occurrence, String code, String token) throws Exception {
        return request("POST", ROOT + "/occurrences/" + occurrence + "/scan-member", token,
            Map.of("requestId", UUID.randomUUID(), "credential", code));
    }
    @Test
    void catalogueIsExplicitPrivateAndBounded() throws Exception {
        UUID occurrence = nearOccurrence(2);
        assertThat(request("GET", "/api/member/events", null, null).status()).isEqualTo(401);
        assertThat(request("GET", "/api/member/events", member, null).body()).doesNotContain(occurrence.toString());
        assertThat(request("GET", "/api/member/events/" + occurrence, member, null).status()).isEqualTo(404);
        assertThat(book(occurrence, member, UUID.randomUUID()).status()).isEqualTo(404);
        publish(occurrence, true, 0);
        Response catalogue = request("GET", "/api/member/events", member, null);
        assertThat(catalogue.status()).isEqualTo(200);
        assertThat(catalogue.body()).contains(occurrence.toString())
            .doesNotContain("personId", "actorId", "requestHash");
        assertThat(request("GET", "/api/member/events?limit=101", member, null).status()).isEqualTo(400);
        assertThat(request("GET", ROOT + "/occurrences/" + occurrence + "/registrations", member, null).status())
            .isEqualTo(403);
        assertThat(request("PUT", ROOT + "/" + eventId(occurrence) + "/member-offer", member,
            Map.of("enabled", true, "expectedVersion", 1)).status()).isEqualTo(403);
    }
    @Test
    void registrationDerivesIdentityAndRetriesDoNotDuplicate() throws Exception {
        UUID occurrence = nearOccurrence(2);
        publish(occurrence, true, 0);
        UUID key = UUID.randomUUID();
        UUID booking = created(book(occurrence, member, key));
        assertThat(created(book(occurrence, member, key))).isEqualTo(booking);
        assertThat(
            db.selectFrom(EVENT_REGISTRATIONS).where(EVENT_REGISTRATIONS.ID.eq(booking)).fetchOne().getPersonId())
            .isEqualTo(person);
        assertThat(request("GET", "/api/member/events?mine=true", member, null).body()).contains("CONFIRMED");
        assertThat(db.fetchCount(EVENT_REGISTRATIONS)).isEqualTo(1);
        Response inbox = request("GET", "/api/member/notifications", member, null);
        assertThat(inbox.data()).containsEntry("unreadCount", 1);
        assertThat(inbox.body()).contains("Your event booking was updated").doesNotContain("personId", "sourceKey");
        assertThat(request("GET", "/api/member/notifications", admin, null).data()).containsEntry("unreadCount", 0);
    }
    @Test
    void cancellationPromotesWaitlistAndRequiresCurrentVersion() throws Exception {
        UUID occurrence = nearOccurrence(1);
        publish(occurrence, true, 0);
        UUID second = createUser("second", "second@example.org", "Second"), secondPerson = person("Second");
        String secondToken = login("second", PASSWORD);
        link(second, secondPerson, true, 0);
        created(book(occurrence, member, UUID.randomUUID()));
        created(book(occurrence, secondToken, UUID.randomUUID()));
        assertThat(request("GET", "/api/member/events/" + occurrence, secondToken, null).body()).contains("WAITLISTED");
        assertThat(
            request("POST", "/api/member/events/" + occurrence + "/cancel", secondToken, Map.of("expectedVersion", 9))
                .status())
            .isEqualTo(409);
        assertThat(request("POST", "/api/member/events/" + occurrence + "/cancel", member, Map.of("expectedVersion", 0))
            .status()).isEqualTo(204);
        assertThat(request("GET", "/api/member/events/" + occurrence, secondToken, null).body()).contains("CONFIRMED");
        assertThat(request("POST", "/api/member/events/" + occurrence + "/registration", member,
            Map.of("requestId", UUID.randomUUID(), "expectedVersion", 1)).status()).isEqualTo(201);
        assertThat(request("GET", "/api/member/events/" + occurrence, member, null).body()).contains("WAITLISTED");
    }
    @Test
    void withdrawingPreservesOwnBookingAndRevocationRemovesAccess() throws Exception {
        UUID occurrence = nearOccurrence(2);
        publish(occurrence, true, 0);
        created(book(occurrence, member, UUID.randomUUID()));
        publish(occurrence, false, 1);
        assertThat(request("GET", "/api/member/events", member, null).body()).doesNotContain(occurrence.toString());
        assertThat(request("GET", "/api/member/events?mine=true", member, null).body()).contains(occurrence.toString());
        assertThat(request("POST", "/api/member/events/" + occurrence + "/cancel", member, Map.of("expectedVersion", 0))
            .status()).isEqualTo(204);
        link(account, person, false, 1);
        assertThat(request("GET", "/api/member/events", member, null).body()).contains("\"verified\":false");
        assertThat(book(occurrence, member, UUID.randomUUID()).status()).isEqualTo(403);
    }
    @Test
    void qrIsStaffOnlySingleUseAndRecordsAnArrival() throws Exception {
        UUID occurrence = nearOccurrence(2);
        publish(occurrence, true, 0);
        created(book(occurrence, member, UUID.randomUUID()));
        String code = pass(occurrence);
        assertThat(scan(occurrence, code, member).status()).isEqualTo(403);
        assertThat(scan(UUID.randomUUID(), code, admin).status()).isEqualTo(404);
        assertThat(scan(occurrence, code, admin).status()).isEqualTo(201);
        assertThat(scan(occurrence, code, admin).status()).isEqualTo(409);
        assertThat(db.fetchCount(EVENT_ATTENDANCE)).isEqualTo(1);
        assertThat(db.selectFrom(EVENT_ADMISSION_PASSES).fetchOne().getTokenHash()).doesNotContain(code);
    }
    @Test
    void expiredScheduleChangedAndRevokedIdentityCodesCannotAdmit() throws Exception {
        UUID occurrence = nearOccurrence(2);
        publish(occurrence, true, 0);
        created(book(occurrence, member, UUID.randomUUID()));
        String expired = pass(occurrence);
        db.update(EVENT_ADMISSION_PASSES)
            .set(EVENT_ADMISSION_PASSES.EXPIRES_AT, OffsetDateTime.now().minusSeconds(1))
            .execute();
        assertThat(scan(occurrence, expired, admin).status()).isEqualTo(409);
        String stale = pass(occurrence);
        db.update(EVENT_OCCURRENCES)
            .set(EVENT_OCCURRENCES.SCHEDULE_REVISION, 1L)
            .where(EVENT_OCCURRENCES.ID.eq(occurrence))
            .execute();
        assertThat(scan(occurrence, stale, admin).status()).isEqualTo(409);
        String revoked = pass(occurrence);
        link(account, person, false, 1);
        assertThat(scan(occurrence, revoked, admin).status()).isEqualTo(409);
        assertThat(db.fetchCount(EVENT_ATTENDANCE)).isZero();
    }
    @Test
    void cancelledBookingsAndSignedOutSessionsInvalidateCodes() throws Exception {
        UUID occurrence = nearOccurrence(2);
        publish(occurrence, true, 0);
        created(book(occurrence, member, UUID.randomUUID()));
        String code = pass(occurrence);
        assertThat(request("POST", "/api/member/events/" + occurrence + "/cancel", member, Map.of("expectedVersion", 0))
            .status()).isEqualTo(204);
        assertThat(scan(occurrence, code, admin).status()).isEqualTo(409);
        assertThat(request("POST", "/api/member/events/" + occurrence + "/registration", member,
            Map.of("requestId", UUID.randomUUID(), "expectedVersion", 1)).status()).isEqualTo(201);
        String signedOut = pass(occurrence);
        assertThat(request("POST", "/api/logout", member, null).status()).isEqualTo(204);
        assertThat(scan(occurrence, signedOut, admin).status()).isEqualTo(409);
        assertThat(db.fetchCount(EVENT_ATTENDANCE)).isZero();
    }
    @Test
    void waitlistedAndUnbookedMembersCannotIssuePasses() throws Exception {
        UUID occurrence = nearOccurrence(1);
        publish(occurrence, true, 0);
        assertThat(request("POST", "/api/member/events/" + occurrence + "/attendance-pass", member, null).status())
            .isEqualTo(404);
        register(occurrence, person("Booked"));
        created(book(occurrence, member, UUID.randomUUID()));
        assertThat(request("POST", "/api/member/events/" + occurrence + "/attendance-pass", member, null).status())
            .isEqualTo(409);
        assertThat(scan(occurrence, "child-collection-code", admin).status()).isEqualTo(400);
    }
}
