package org.onechurch.childrenministry.messaging.presentation;
import static org.assertj.core.api.Assertions.assertThat;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_DELIVERY_EVENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_EMAIL_DELIVERIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_CONTACT_METHODS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PERMISSIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.ROLE_PERMISSIONS;

import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.onechurch.communications.application.DeliveryProcessor;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.communications.application.contract.EmailTransport;
import org.onechurch.shared.infrastructure.ChildrenIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.TestConfiguration;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Import;
import org.springframework.context.annotation.Primary;
import org.springframework.test.context.TestPropertySource;

import tools.jackson.databind.json.JsonMapper;

import java.time.Instant;
import java.time.OffsetDateTime;
import java.util.List;
import java.util.Map;
import java.util.UUID;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.CopyOnWriteArrayList;
@Import(EmailDeliveryApiIntegrationTest.Configuration.class)
@TestPropertySource(properties = "onechurch.email.poll-ms=3600000")
class EmailDeliveryApiIntegrationTest extends ChildrenIntegrationTestSupport {
    @TestConfiguration
    static class Configuration {
        @Bean
        @Primary
        FakeTransport testTransport() {
            return new FakeTransport();
        }
    }
    static class FakeTransport implements EmailTransport {
        volatile boolean enabled = false;
        volatile boolean capture = false;
        volatile String outcome = "SENT";
        final List<DeliverySnapshot> sent = new CopyOnWriteArrayList<>();
        public Configuration configuration() {
            return new Configuration(enabled, capture, "Test SMTP");
        }
        public Result send(DeliverySnapshot d) {
            sent.add(d);
            return new Result(outcome, outcome.equals("SENT") ? "" : "TEST_FAILURE", capture);
        }
    }
    @Autowired private FakeTransport transport;
    @Autowired private DeliveryProcessor processor;
    @Autowired private JsonMapper mapper;
    private UUID communication, contact, verification;
    @BeforeEach
    void prepare() throws Exception {
        transport.enabled = true;
        transport.capture = false;
        transport.outcome = "SENT";
        transport.sent.clear();
        contact = created(request("POST", "/api/people/" + guardian + "/contacts", admin,
            body("expectedVersion", 0, "kind", "EMAIL", "value", "guardian@example.org", "primary", true)));
        communication = create("/guardian-communications", body("requestId", UUID.randomUUID(), "childId", child,
            "authorityId", authority, "programmeId", programme, "type", "CONSENT_RENEWAL", "dueOn", today));
        verification = create("/guardian-communications/" + communication + "/verify-email",
            body("contactId", contact, "evidence", "Confirmed directly with guardian", "validUntil", until));
    }
    private Map<String, Object> queueBody(Instant at) throws Exception {
        Response review = call("GET", "/guardian-communications/" + communication + "/email-review", null);
        String hash = mapper.readTree(review.body()).path("recipients").get(0).path("reviewHash").asText();
        return body("expectedVersion", 0, "verificationId", verification, "reviewHash", hash, "scheduledAt", at);
    }
    private UUID queue() throws Exception {
        return create("/guardian-communications/" + communication + "/queue-email", queueBody(null));
    }
    private String state(UUID id) {
        return db.select(COMMUNICATION_EMAIL_DELIVERIES.STATUS)
            .from(COMMUNICATION_EMAIL_DELIVERIES)
            .where(COMMUNICATION_EMAIL_DELIVERIES.ID.eq(id))
            .fetchOne(COMMUNICATION_EMAIL_DELIVERIES.STATUS);
    }
    private long version(UUID id) {
        return db.select(COMMUNICATION_EMAIL_DELIVERIES.VERSION)
            .from(COMMUNICATION_EMAIL_DELIVERIES)
            .where(COMMUNICATION_EMAIL_DELIVERIES.ID.eq(id))
            .fetchOne(COMMUNICATION_EMAIL_DELIVERIES.VERSION);
    }
    @Test
    void reviewedQueueIsUniqueAndConcurrentDispatchSendsOnce() throws Exception {
        Map<String, Object> body = queueBody(null);
        UUID id = create("/guardian-communications/" + communication + "/queue-email", body);
        assertThat(call("POST", "/guardian-communications/" + communication + "/queue-email", body).status())
            .isEqualTo(409);
        CompletableFuture<Void> first = CompletableFuture.runAsync(() -> processor.dispatch(id));
        CompletableFuture<Void> second = CompletableFuture.runAsync(() -> processor.dispatch(id));
        first.join();
        second.join();
        assertThat(transport.sent).hasSize(1);
        assertThat(transport.sent.getFirst().recipient()).isEqualTo("guardian@example.org");
        assertThat(state(id)).isEqualTo("SENT");
        assertThat(call("GET", "/email-deliveries", null).body()).contains("SENT")
            .doesNotContain("guardian@example.org", "please contact");
        assertThat(call("GET", "/email-deliveries/" + id, null).body()).contains("guardian@example.org", "SENDING",
            "SENT");
        assertThat(call("POST", "/guardian-communications/" + communication + "/contact",
            body("expectedVersion", 0, "status", "CONTACTED", "reference", "Manual contact")).status()).isEqualTo(409);
    }
    @Test
    void futureScheduleDoesNotDispatchAndCancellationIsVersioned() throws Exception {
        UUID id = create("/guardian-communications/" + communication + "/queue-email",
            queueBody(Instant.now().plusSeconds(3600)));
        processor.runDue();
        assertThat(transport.sent).isEmpty();
        assertThat(state(id)).isEqualTo("SCHEDULED");
        assertThat(
            call("POST", "/email-deliveries/" + id + "/cancel", body("expectedVersion", 9, "reason", "Wrong revision"))
                .status())
            .isEqualTo(409);
        ok("POST", "/email-deliveries/" + id + "/cancel",
            body("expectedVersion", 0, "reason", "Guardian contacted separately"));
        processor.runDue();
        assertThat(transport.sent).isEmpty();
        ok("POST", "/guardian-communications/" + communication + "/contact",
            body("expectedVersion", 0, "status", "CONTACTED", "reference", "Telephone confirmed"));
    }
    @Test
    void revokedEmailAndSenderPermissionChangesBlockScheduledEffects() throws Exception {
        UUID id = queue();
        ok("POST", "/verified-emails/" + verification + "/revoke", body("reason", "Guardian changed address"));
        processor.dispatch(id);
        assertThat(state(id)).isEqualTo("BLOCKED");
        assertThat(transport.sent).isEmpty();
        assertThat(call("POST", "/email-deliveries/" + id + "/retry",
            body("expectedVersion", version(id), "reason", "Try invalid address")).status()).isEqualTo(409);
    }
    @Test
    void senderLosingPermissionIsRecheckedByTheBackgroundProcessor() throws Exception {
        UUID id = queue();
        db.deleteFrom(ROLE_PERMISSIONS)
            .where(ROLE_PERMISSIONS.PERMISSION_ID.in(
                db.select(PERMISSIONS.ID).from(PERMISSIONS).where(PERMISSIONS.CODE.eq("children.communications.send"))))
            .execute();
        processor.dispatch(id);
        assertThat(state(id)).isEqualTo("BLOCKED");
        assertThat(transport.sent).isEmpty();
    }
    @Test
    void endingThePeopleContactInvalidatesItsRecordedVerification() throws Exception {
        UUID id = queue();
        db.update(PEOPLE_CONTACT_METHODS)
            .set(PEOPLE_CONTACT_METHODS.ENDED_AT, OffsetDateTime.now())
            .where(PEOPLE_CONTACT_METHODS.ID.eq(contact))
            .execute();
        processor.dispatch(id);
        assertThat(state(id)).isEqualTo("BLOCKED");
        assertThat(transport.sent).isEmpty();
    }
    @Test
    void uncertainTransportAndInterruptedWorkersCannotAutomaticallyResend() throws Exception {
        transport.outcome = "UNKNOWN";
        UUID id = queue();
        processor.dispatch(id);
        processor.runDue();
        assertThat(transport.sent).hasSize(1);
        assertThat(state(id)).isEqualTo("UNKNOWN");
        assertThat(call("POST", "/email-deliveries/" + id + "/retry",
            body("expectedVersion", version(id), "reason", "Unsafe retry")).status()).isEqualTo(409);
        assertThat(call("POST", "/email-deliveries/" + id + "/cancel",
            body("expectedVersion", version(id), "reason", "Unsafe cancellation")).status()).isEqualTo(409);
    }
    @Test
    void definiteFailureCanBeExplicitlyRetriedButAttemptLimitIsBounded() throws Exception {
        transport.outcome = "FAILED";
        UUID id = queue();
        processor.dispatch(id);
        for (int attempt = 1; attempt < 3; attempt++) {
            ok("POST", "/email-deliveries/" + id + "/retry",
                body("expectedVersion", version(id), "reason", "Configuration corrected"));
            processor.dispatch(id);
        }
        assertThat(transport.sent).hasSize(3);
        assertThat(call("POST", "/email-deliveries/" + id + "/retry",
            body("expectedVersion", version(id), "reason", "Too many attempts")).status()).isEqualTo(409);
    }
    @Test
    void deliveredRequiresCorrelatedIdempotentTrustedReceiptAndCaptureCannotClaimDelivery() throws Exception {
        UUID id = queue();
        processor.dispatch(id);
        String messageId = transport.sent.getFirst().messageId();
        Map<String, Object> receipt = body("deliveryId", id, "messageId", messageId, "eventKey", "provider:event-1",
            "status", "DELIVERED", "reference", "Authenticated provider evidence");
        ok("POST", "/email-deliveries/receipts", receipt);
        ok("POST", "/email-deliveries/receipts", receipt);
        assertThat(state(id)).isEqualTo("DELIVERED");
        assertThat(db.fetchCount(COMMUNICATION_DELIVERY_EVENTS,
            COMMUNICATION_DELIVERY_EVENTS.EVENT_KEY.eq("provider:event-1"))).isEqualTo(1);
        receipt.put("status", "FAILED");
        assertThat(call("POST", "/email-deliveries/receipts", receipt).status()).isEqualTo(409);
    }
    @Test
    void changedApprovalFingerprintAndUnverifiedAddressCannotQueue() throws Exception {
        Map<String, Object> body = queueBody(null);
        body.put("reviewHash", "stale");
        assertThat(call("POST", "/guardian-communications/" + communication + "/queue-email", body).status())
            .isEqualTo(409);
        body = queueBody(null);
        body.put("verificationId", UUID.randomUUID());
        assertThat(call("POST", "/guardian-communications/" + communication + "/queue-email", body).status())
            .isEqualTo(409);
        assertThat(db.fetchCount(COMMUNICATION_EMAIL_DELIVERIES)).isZero();
    }
    @Test
    void abandonedSendingClaimBecomesUncertainWithoutResending() throws Exception {
        UUID id = queue();
        db.update(COMMUNICATION_EMAIL_DELIVERIES)
            .set(COMMUNICATION_EMAIL_DELIVERIES.STATUS, "SENDING")
            .set(COMMUNICATION_EMAIL_DELIVERIES.STARTED_AT, OffsetDateTime.now().minusMinutes(10))
            .set(COMMUNICATION_EMAIL_DELIVERIES.ATTEMPT_ID, UUID.randomUUID())
            .where(COMMUNICATION_EMAIL_DELIVERIES.ID.eq(id))
            .execute();
        processor.runDue();
        assertThat(state(id)).isEqualTo("UNKNOWN");
        assertThat(transport.sent).isEmpty();
    }
    @Test
    void localCaptureIsNeverRepresentedAsRealDelivery() throws Exception {
        transport.capture = true;
        UUID id = queue();
        processor.dispatch(id);
        assertThat(call("GET", "/email-deliveries/" + id, null).body()).contains("\"captured\":true",
            "\"status\":\"SENT\"");
        assertThat(call("POST", "/email-deliveries/receipts",
            body("deliveryId", id, "messageId", transport.sent.getFirst().messageId(), "eventKey", "capture-event",
                "status", "DELIVERED", "reference", "Not a real provider"))
            .status()).isEqualTo(409);
    }

    @Test
    void templateChangesInvalidateOldReviewsAndNeverRewriteAnApprovedDelivery() throws Exception {
        for (String code : List.of("communications.templates.read", "communications.templates.manage")) {
            UUID permission = UUID.randomUUID();
            db.insertInto(PERMISSIONS).set(PERMISSIONS.ID, permission).set(PERMISSIONS.CODE, code).execute();
            db.insertInto(ROLE_PERMISSIONS)
                .set(ROLE_PERMISSIONS.ROLE_ID, ADMIN_ROLE)
                .set(ROLE_PERMISSIONS.PERMISSION_ID, permission)
                .execute();
        }
        UUID template = UUID.fromString("10000000-0000-0000-0000-000000000001");
        org.onechurch.shared.infrastructure.jooq.tables.CommunicationTemplates table =
            org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_TEMPLATES;
        org.onechurch.shared.infrastructure.jooq.tables.CommunicationTemplateRevisions revisions =
            org.onechurch.shared.infrastructure.jooq.Tables.COMMUNICATION_TEMPLATE_REVISIONS;
        org.onechurch.shared.infrastructure.jooq.tables.records.CommunicationTemplatesRecord original =
            db.selectFrom(table).where(table.ID.eq(template)).fetchOne();
        try {
            Map<String, Object> oldReview = queueBody(null);
            editAndPublishTemplate(template, 0, "First revision");
            assertThat(call("POST", "/guardian-communications/" + communication + "/queue-email", oldReview).status())
                .isEqualTo(409);
            UUID delivery = queue();
            String frozen = db.select(COMMUNICATION_EMAIL_DELIVERIES.BODY)
                .from(COMMUNICATION_EMAIL_DELIVERIES)
                .where(COMMUNICATION_EMAIL_DELIVERIES.ID.eq(delivery))
                .fetchOne(COMMUNICATION_EMAIL_DELIVERIES.BODY);
            assertThat(frozen).contains("First revision");
            editAndPublishTemplate(template, 2, "Later revision");
            processor.dispatch(delivery);
            assertThat(state(delivery)).isEqualTo("SENT");
            assertThat(transport.sent).hasSize(1);
            assertThat(transport.sent.getFirst().body()).isEqualTo(frozen).doesNotContain("Later revision");
            assertThat(transport.sent.getFirst().contentType()).isEqualTo("text/html");
        } finally {
            db.deleteFrom(revisions).where(revisions.TEMPLATE_ID.eq(template).and(revisions.VERSION.gt(0L))).execute();
            db.update(table)
                .set(table.NAME, original.getName())
                .set(table.SUBJECT, original.getSubject())
                .set(table.HTML, original.getHtml())
                .set(table.VARIABLES, original.getVariables())
                .set(table.STATUS, original.getStatus())
                .set(table.VERSION, original.getVersion())
                .set(table.PUBLISHED_VERSION, original.getPublishedVersion())
                .set(table.UPDATED_BY, original.getUpdatedBy())
                .set(table.UPDATED_AT, original.getUpdatedAt())
                .where(table.ID.eq(template))
                .execute();
        }
    }
    private void editAndPublishTemplate(UUID id, long expected, String introduction) {
        io.restassured.RestAssured.given()
            .baseUri("http://localhost")
            .port(port)
            .auth()
            .oauth2(admin)
            .contentType("application/json")
            .body(mapper.writeValueAsString(
                body("expectedVersion", expected, "name", "Consent renewal", "subject", "Consent renewal request",
                    "html", "<h2>" + introduction + "</h2><p>{{message}}</p>", "variables", List.of("message"))))
            .put("/api/communications/templates/" + id)
            .then()
            .statusCode(200);
        io.restassured.RestAssured.given()
            .baseUri("http://localhost")
            .port(port)
            .auth()
            .oauth2(admin)
            .contentType("application/json")
            .body(mapper.writeValueAsString(body("expectedVersion", expected + 1)))
            .post("/api/communications/templates/" + id + "/publish")
            .then()
            .statusCode(200);
    }
}
