package org.onechurch.communications.application;
import org.onechurch.communications.application.contract.CommunicationAudit;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.communications.application.contract.DeliverySources;
import org.onechurch.communications.application.contract.EmailDeliveries;
import org.onechurch.communications.application.contract.EmailTransport;
import org.onechurch.communications.domain.CommunicationException;
import org.onechurch.communications.domain.CommunicationRules;
import org.onechurch.communications.domain.EmailDelivery;
import org.onechurch.communications.domain.repository.DeliveryRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public final class DeliveryLifecycle implements EmailDeliveries {
    private final DeliveryReader reader;
    private final DeliveryRepository writer;
    private final DeliverySources sources;
    private final DispatchEligibility eligibility;
    private final UnitOfWork tx;
    private final CommunicationAudit audit;
    private final Clock clock;
    private final EmailTransport transport;
    public DeliveryLifecycle(DeliveryReader reader, DeliveryRepository writer, DeliverySources sources,
        DispatchEligibility eligibility, UnitOfWork tx, CommunicationAudit audit, Clock clock,
        EmailTransport transport) {
        this.reader = reader;
        this.writer = writer;
        this.sources = sources;
        this.eligibility = eligibility;
        this.tx = tx;
        this.audit = audit;
        this.clock = clock;
        this.transport = transport;
    }
    public void queue(String sourceContext, DeliverySnapshot approved) {
        EmailDelivery delivery = DeliveryMapping.aggregate(approved);
        if (!delivery.waiting() || delivery.attempts() != 0 || delivery.version() != 0) {
            throw CommunicationException.invalid("A new approved delivery is required.");
        }
        tx.execute(() -> {
            writer.insert(delivery);
            sources.associate(delivery.id(), sourceContext);
            eligibility.authorize(delivery, delivery.approvedBy(), "queue");
            eligibility.require(delivery, clock.instant());
            audit.record(delivery.approvedBy(), "communication.email.queued", delivery.id(), "", clock.instant());
            writer.event(delivery.id(), delivery.status(), clock.instant(), delivery.approvedBy(), null, null,
                "Reviewed recipient and message approved.");
            return null;
        });
    }
    public void cancel(UUID actor, UUID id, long expected, String reason) {
        String note = CommunicationRules.text(reason, 500);
        tx.execute(() -> {
            EmailDelivery d = writer.lock(id).orElseThrow(() -> CommunicationException.missing("Delivery"));
            eligibility.authorize(d, actor, "cancel");
            CommunicationRules.version(d.version(), expected);
            if (!List.of("QUEUED", "SCHEDULED", "FAILED", "BLOCKED").contains(d.status())) {
                throw CommunicationException
                    .conflict("An in-progress, accepted or uncertain send cannot be cancelled.");
            }
            writer.save(d.transition("CANCELLED", d.scheduledAt(), clock.instant(), "", d.captured()));
            writer.event(id, "CANCELLED", clock.instant(), actor, null, null, note);
            audit.record(actor, "communication.email.cancelled", id, "", clock.instant());
            return null;
        });
    }
    public void retry(UUID actor, UUID id, long expected, String reason) {
        String note = CommunicationRules.text(reason, 500);
        tx.execute(() -> {
            EmailDelivery d = writer.lock(id).orElseThrow(() -> CommunicationException.missing("Delivery"));
            eligibility.authorize(d, actor, "retry");
            CommunicationRules.version(d.version(), expected);
            if (!List.of("FAILED", "BLOCKED").contains(d.status())) {
                throw CommunicationException.conflict(
                    "Only definite failures or blocked deliveries may be retried. Reconcile uncertain outcomes with the provider.");
            }
            if (d.attempts() >= 3) {
                throw CommunicationException.conflict("The delivery attempt limit is reached.");
            }
            EmailDelivery approved = d.approveRetry(actor, clock.instant());
            eligibility.require(approved, clock.instant());
            writer.save(approved);
            writer.event(id, "RETRY_APPROVED", clock.instant(), actor, null, null, note);
            audit.record(actor, "communication.email.retry", id, "", clock.instant());
            return null;
        });
    }
    public void receipt(UUID actor, UUID id, String messageId, String key, String status, String reference) {
        String eventKey = CommunicationRules.text(key, 200);
        String ref = CommunicationRules.text(reference, 500);
        if (status == null || !List.of("DELIVERED", "FAILED").contains(status)) {
            throw CommunicationException.invalid("Choose a delivered or failed provider outcome.");
        }
        String hash = DeliveryFingerprint.of(id, messageId, status, ref);
        tx.execute(() -> {
            EmailDelivery d = writer.lock(id).orElseThrow(() -> CommunicationException.missing("Delivery"));
            eligibility.authorize(d, actor, "receipt");
            Optional<DeliveryReader.Receipt> prior = reader.receipt(eventKey);
            if (prior.isPresent()) {
                if (!prior.orElseThrow().hash().equals(hash) || !prior.orElseThrow().deliveryId().equals(id)) {
                    throw CommunicationException.conflict("Provider event identifier already used.");
                }
                return null;
            }
            if (transport.configuration().capture() || d.captured() || !d.messageId().equals(messageId)
                || !List.of("SENT", "UNKNOWN").contains(d.status())) {
                throw CommunicationException.conflict("Receipt does not match a live SMTP-accepted delivery.");
            }
            writer.save(d.transition(status, d.scheduledAt(), clock.instant(),
                status.equals("FAILED") ? "PROVIDER_FAILURE" : "", false));
            writer.event(id, status, clock.instant(), actor, eventKey, hash, ref);
            audit.record(actor, "communication.email.receipt", id, "", clock.instant());
            return null;
        });
    }
}
