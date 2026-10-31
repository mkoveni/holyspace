package org.onechurch.volunteering.presentation;

import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_REMINDERS;

import org.junit.jupiter.api.Test;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.shared.infrastructure.VolunteerIntegrationTestSupport;
import org.onechurch.volunteering.infrastructure.VolunteerReminderEligibility;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.beans.factory.annotation.Qualifier;

import java.time.Instant;
import java.util.Map;
import java.util.UUID;

class VolunteerRemindersApiIntegrationTest extends VolunteerIntegrationTestSupport {
    @Autowired VolunteerReminderEligibility eligibility;
    @Autowired
    @Qualifier("communicationDeliveryStore") DeliveryReader deliveries;
    private UUID contact() throws Exception {
        UUID person = UUID.fromString(
            (String) ((Map<?, ?>) ((Map<?, ?>) serving("GET", "/volunteers/" + volunteer, null).data().get("person"))
                .get("volunteer")).get("personId"));
        return created(request("POST", "/api/people/" + person + "/contacts", admin,
            body("expectedVersion", 0, "kind", "EMAIL", "value", "solly@example.org", "primary", true)));
    }
    @Test
    void reminderFreezesEscapedPublishedContentAndCancellationInvalidatesDispatch() throws Exception {
        UUID assignment = book(volunteer, "CONFIRMED"), contact = contact();
        String message = "See you <b>soon</b>";
        Response preview = serving("POST", "/assignments/" + assignment + "/reminder-review",
            body("contactId", contact, "message", message));
        assertThat(preview.status()).withFailMessage(preview.body()).isEqualTo(200);
        assertThat(preview.body()).contains("&lt;b&gt;").doesNotContain("<b>soon");
        UUID request = UUID.randomUUID();
        Response queued = serving("POST", "/assignments/" + assignment + "/reminders",
            body("requestId", request, "contactId", contact, "message", message, "reviewHash",
                preview.data().get("hash"), "verificationEvidence",
                "Volunteer confirmed own email and reminder permission"));
        assertThat(queued.status()).withFailMessage(queued.body()).isEqualTo(200);
        UUID deliveryId = db.select(SERVING_REMINDERS.DELIVERY_ID)
            .from(SERVING_REMINDERS)
            .where(SERVING_REMINDERS.ID.eq(request))
            .fetchSingle(SERVING_REMINDERS.DELIVERY_ID);
        DeliverySnapshot frozen = deliveries.delivery(deliveryId).orElseThrow();
        assertThat(frozen.body()).isEqualTo(preview.data().get("html"));
        assertThat(eligibility.eligible(frozen, Instant.now())).isTrue();
        assertThat(serving("POST", "/assignments/" + assignment + "/status",
            body("expectedVersion", 0, "status", "CANCELLED", "reason", "Unable to serve")).status()).isEqualTo(204);
        assertThat(eligibility.eligible(frozen, Instant.now())).isFalse();
        assertThat(serving("GET", "/deliveries", null).body()).doesNotContain("solly@example.org")
            .doesNotContain("See you");
    }
    @Test
    void staleReviewAndBlankVerificationCannotQueueMessages() throws Exception {
        UUID assignment = book(volunteer, "CONFIRMED"), contact = contact();
        Response preview = serving("POST", "/assignments/" + assignment + "/reminder-review",
            body("contactId", contact, "message", "Welcome"));
        assertThat(serving("PUT", "/shifts/" + shift, body("expectedVersion", shiftVersion(shift), "startsAt",
            start.plusSeconds(60), "endsAt", end.plusSeconds(60), "positions", 2, "status", "OPEN")).status())
            .isEqualTo(204);
        assertThat(serving("POST", "/assignments/" + assignment + "/reminders",
            body("requestId", UUID.randomUUID(), "contactId", contact, "message", "Welcome", "reviewHash",
                preview.data().get("hash"), "verificationEvidence", "Verified by phone"))
            .status()).isEqualTo(409);
        assertThat(serving("POST", "/assignments/" + assignment + "/reminders",
            body("requestId", UUID.randomUUID(), "contactId", contact, "message", "Welcome", "reviewHash",
                preview.data().get("hash"), "verificationEvidence", " "))
            .status()).isEqualTo(400);
    }
}
