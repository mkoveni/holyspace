package org.onechurch.events.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.EVENT_NOTIFICATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_CONTACT_METHODS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.Test;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.events.infrastructure.EventNotificationEligibility;
import org.onechurch.shared.infrastructure.EventDayIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.beans.factory.annotation.Qualifier;

import java.time.Instant;
import java.util.Map;
import java.util.UUID;
class EventNotificationsApiIntegrationTest extends EventDayIntegrationTestSupport {
    @Autowired EventNotificationEligibility eligibility;
    @Autowired
    @Qualifier("communicationDeliveryStore") DeliveryReader deliveries;
    private UUID contact(UUID person, String email) throws Exception {
        return created(request("POST", "/api/people/" + person + "/contacts", admin,
            Map.of("expectedVersion", 0, "kind", "EMAIL", "value", email, "primary", true)));
    }
    private Response review(UUID booking, UUID contact, String purpose) throws Exception {
        return request("POST", ROOT + "/registrations/" + booking + "/notification-review", admin,
            Map.of("contactId", contact, "purpose", purpose, "message", "Welcome <b>friend</b>"));
    }
    private Map<String, Object> approval(UUID contact, String hash, UUID key, String purpose) {
        return Map.of("requestId", key, "contactId", contact, "purpose", purpose, "message", "Welcome <b>friend</b>",
            "reviewHash", hash, "verificationEvidence", "Recipient confirmed own address and communication permission");
    }
    @Test
    void noticesFreezeReviewedHtmlAndChangesBlockDispatch() throws Exception {
        UUID occurrence = nearOccurrence(2), person = person("Solly"), booking = register(occurrence, person),
            contact = contact(person, "solly@example.org");
        Response preview = review(booking, contact, "CONFIRMATION");
        assertThat(preview.status()).withFailMessage(preview.body()).isEqualTo(200);
        assertThat(preview.body()).contains("&lt;b&gt;").doesNotContain("<b>friend</b>");
        UUID key = UUID.randomUUID();
        Map<String, Object> body = approval(contact, (String) preview.data().get("hash"), key, "CONFIRMATION");
        UUID id = created(request("POST", ROOT + "/registrations/" + booking + "/notifications", admin, body));
        assertThat(created(request("POST", ROOT + "/registrations/" + booking + "/notifications", admin, body)))
            .isEqualTo(id);
        assertThat(db.fetchCount(EVENT_NOTIFICATIONS)).isEqualTo(1);
        DeliverySnapshot frozen = deliveries.delivery(id).orElseThrow();
        assertThat(frozen.body()).isEqualTo(preview.data().get("html"));
        assertThat(eligibility.eligible(frozen, Instant.now())).isTrue();
        assertThat(request("POST", ROOT + "/registrations/" + booking + "/cancel", admin,
            Map.of("expectedVersion", 0, "reason", "Unable to attend")).status()).isEqualTo(204);
        assertThat(eligibility.eligible(frozen, Instant.now())).isFalse();
        Response cancellation = review(booking, contact, "CANCELLATION");
        assertThat(cancellation.status()).isEqualTo(200);
        UUID cancellationDelivery = created(request("POST", ROOT + "/registrations/" + booking + "/notifications",
            admin, approval(contact, (String) cancellation.data().get("hash"), UUID.randomUUID(), "CANCELLATION")));
        assertThat(eligibility.eligible(deliveries.delivery(cancellationDelivery).orElseThrow(), Instant.now()))
            .isTrue();
        Response tracked = request("GET", ROOT + "/deliveries", admin, null);
        assertThat(tracked.status()).withFailMessage(tracked.body()).isEqualTo(200);
        assertThat(tracked.data().get("total")).isEqualTo(2);
        assertThat(tracked.body()).doesNotContain("solly@example.org", "Welcome &lt;");
    }
    @Test
    void foreignContactsAndStaleReviewsCannotBeApproved() throws Exception {
        UUID occurrence = nearOccurrence(2), person = person("Owner"), booking = register(occurrence, person),
            contact = contact(person, "owner@example.org"), other = contact(person("Other"), "other@example.org");
        assertThat(review(booking, other, "CONFIRMATION").status()).isEqualTo(409);
        Response preview = review(booking, contact, "REMINDER");
        db.update(PEOPLE_CONTACT_METHODS)
            .set(PEOPLE_CONTACT_METHODS.VALUE, "updated@example.org")
            .where(PEOPLE_CONTACT_METHODS.ID.eq(contact))
            .execute();
        assertThat(request("POST", ROOT + "/registrations/" + booking + "/notifications", admin,
            approval(contact, (String) preview.data().get("hash"), UUID.randomUUID(), "REMINDER")).status())
            .isEqualTo(409);
        assertThat(db.fetchCount(EVENT_NOTIFICATIONS)).isZero();
    }
    @Test
    void dispatchRechecksLiveSenderPermissionAndCurrentEmail() throws Exception {
        UUID occurrence = nearOccurrence(2), person = person("Sender test"), booking = register(occurrence, person),
            contact = contact(person, "sender@example.org");
        Response preview = review(booking, contact, "REMINDER");
        UUID id = created(request("POST", ROOT + "/registrations/" + booking + "/notifications", admin,
            approval(contact, (String) preview.data().get("hash"), UUID.randomUUID(), "REMINDER")));
        DeliverySnapshot frozen = deliveries.delivery(id).orElseThrow();
        assertThat(eligibility.eligible(frozen, Instant.now())).isTrue();
        db.deleteFrom(ROLE_PERMISSIONS)
            .where(ROLE_PERMISSIONS.PERMISSION_ID.in(
                db.select(PERMISSIONS.ID).from(PERMISSIONS).where(PERMISSIONS.CODE.eq("events.communications.send"))))
            .execute();
        assertThat(eligibility.eligible(frozen, Instant.now())).isFalse();
    }
}
