package org.onechurch.communications.application;

import org.onechurch.communications.application.contract.CommunicationAudit;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.communications.application.contract.EmailTransport;
import org.onechurch.communications.domain.CommunicationException;
import org.onechurch.communications.domain.EmailDelivery;
import org.onechurch.communications.domain.repository.DeliveryRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.Locale;
import java.util.Optional;
import java.util.UUID;
public final class DeliveryProcessor {
    private final DeliveryReader reader;
    private final DeliveryRepository writer;
    private final DispatchEligibility policy;
    private final EmailTransport transport;
    private final UnitOfWork tx;
    private final CommunicationAudit audit;
    private final Clock clock;
    public DeliveryProcessor(DeliveryReader reader, DeliveryRepository writer, DispatchEligibility policy,
        EmailTransport transport, UnitOfWork tx, CommunicationAudit audit, Clock clock) {
        this.reader = reader;
        this.writer = writer;
        this.policy = policy;
        this.transport = transport;
        this.tx = tx;
        this.audit = audit;
        this.clock = clock;
    }
    public void runDue() {
        if (!transport.configuration().enabled()) {
            return;
        }
        tx.execute(() -> writer.recoverStale(clock.instant().minusSeconds(300), clock.instant()));
        for (UUID id : reader.due(clock.instant(), 25)) {
            dispatch(id);
        }
    }
    public void dispatch(UUID id) {
        Optional<EmailDelivery> claimed = tx.execute(() -> {
            EmailDelivery d = writer.lock(id).orElseThrow(() -> CommunicationException.missing("Delivery"));
            if (!d.waiting() || d.scheduledAt().isAfter(clock.instant())) {
                return Optional.empty();
            }
            try {
                policy.require(d, clock.instant());
            } catch (CommunicationException invalid) {
                writer.save(d.transition("BLOCKED", d.scheduledAt(), clock.instant(), "ELIGIBILITY_CHANGED", false));
                writer.event(id, "BLOCKED", clock.instant(), d.approvedBy(), null, null,
                    "Sender, recipient or ministry eligibility changed. Review before retrying.");
                return Optional.empty();
            }
            EmailDelivery active = d.claim(clock.instant());
            writer.save(active);
            writer.event(id, "SENDING", clock.instant(), d.approvedBy(), null, null, "Dispatch started.");
            return Optional.of(active);
        });
        if (claimed.isEmpty()) {
            return;
        }
        EmailDelivery sending = claimed.orElseThrow();
        EmailTransport.Result result;
        try {
            result = transport.send(DeliveryMapping.snapshot(sending));
        } catch (RuntimeException unknown) {
            result = new EmailTransport.Result("UNKNOWN", "TRANSPORT_UNCERTAIN", false);
        }
        final EmailTransport.Result outcome = result;
        tx.execute(() -> {
            EmailDelivery current = writer.lock(id).orElseThrow();
            if (!current.status().equals("SENDING") || !current.attemptId().equals(sending.attemptId())) {
                return null;
            }
            writer.save(current.transition(outcome.status(), current.scheduledAt(), clock.instant(),
                outcome.errorCode(), outcome.captured()));
            writer.event(id, outcome.status(), clock.instant(), current.approvedBy(), null, null,
                outcome.captured() ? "Captured by the local test inbox." : "Transport outcome recorded.");
            audit.record(current.approvedBy(), "communication.email." + outcome.status().toLowerCase(Locale.ROOT), id,
                "", clock.instant());
            return null;
        });
    }
}
