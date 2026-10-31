package org.onechurch.childrenministry.messaging.application;
import org.onechurch.childrenministry.messaging.application.contract.DeliveryReader;
import org.onechurch.childrenministry.messaging.application.contract.DeliveryWriter;
import org.onechurch.childrenministry.messaging.domain.GuardianCommunication;
import org.onechurch.childrenministry.messaging.domain.VerifiedGuardianEmail;
import org.onechurch.childrenministry.messaging.domain.repository.GuardianCommunicationRepository;
import org.onechurch.childrenministry.shared.application.contract.ChildrenAudit;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.domain.ChildrenRules;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.communications.application.contract.EmailDeliveries;
import org.onechurch.communications.application.contract.EmailTemplates;
import org.onechurch.communications.application.contract.EmailTransport;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.time.temporal.ChronoUnit;
import java.util.Optional;
import java.util.UUID;
public final class DeliveryCommands {
    private final DeliveryReader reader;
    private final DeliveryWriter writer;
    private final DeliveryPolicy policy;
    private final GuardianCommunicationRepository communications;
    private final PersonDirectory people;
    private final PersonEmailDirectory emails;
    private final UnitOfWork tx;
    private final ChildrenAudit audit;
    private final Clock clock;
    private final EmailTransport transport;
    private final EmailDeliveries deliveries;
    public DeliveryCommands(DeliveryReader reader, DeliveryWriter writer, DeliveryPolicy policy,
        GuardianCommunicationRepository communications, PersonDirectory people, PersonEmailDirectory emails,
        UnitOfWork tx, ChildrenAudit audit, Clock clock, EmailTransport transport, EmailDeliveries deliveries) {
        this.reader = reader;
        this.writer = writer;
        this.policy = policy;
        this.communications = communications;
        this.people = people;
        this.emails = emails;
        this.tx = tx;
        this.audit = audit;
        this.clock = clock;
        this.transport = transport;
        this.deliveries = deliveries;
    }
    public UUID verify(UUID actor, UUID communicationId, UUID contactId, String evidence, Instant validUntil) {
        String proof = ChildrenRules.text(evidence, 500);
        return tx.execute(() -> {
            GuardianCommunication c = policy.current(communicationId, clock.instant());
            people.lock(c.guardianId()).orElseThrow(() -> ChildrenException.missing("Guardian"));
            Instant now = clock.instant();
            if (validUntil == null || !validUntil.isAfter(now) || validUntil.isAfter(now.plus(366, ChronoUnit.DAYS))) {
                throw ChildrenException.invalid("Email verification must expire within one year.");
            }
            PersonEmailDirectory.Email contact = emails.active(c.guardianId())
                .stream()
                .filter(e -> e.id().equals(contactId))
                .findFirst()
                .orElseThrow(() -> ChildrenException.invalid("Choose the guardian's active People email contact."));
            UUID id = UUID.randomUUID();
            writer.verify(new VerifiedGuardianEmail(id, c.guardianId(), contact.id(), contact.value(), actor, now,
                validUntil, proof, null));
            audit.record(actor, "guardian.email.verified", id, "", now);
            return id;
        });
    }
    public void revoke(UUID actor, UUID id, String reason) {
        ChildrenRules.text(reason, 500);
        tx.execute(() -> {
            VerifiedGuardianEmail email =
                reader.verification(id).orElseThrow(() -> ChildrenException.missing("Verified email"));
            people.lock(email.guardianId()).orElseThrow(() -> ChildrenException.missing("Guardian"));
            writer.revoke(id, clock.instant());
            audit.record(actor, "guardian.email.revoked", id, "", clock.instant());
            return null;
        });
    }
    public UUID queue(UUID actor, UUID communicationId, long expected, UUID verificationId, String hash,
        Instant scheduled) {
        if (!transport.configuration().enabled()) {
            throw ChildrenException.conflict("Email delivery is not configured.");
        }
        return tx.execute(() -> {
            GuardianCommunication c =
                communications.lock(communicationId).orElseThrow(() -> ChildrenException.missing("Communication"));
            Instant now = clock.instant();
            ChildrenRules.version(c.version(), expected);
            policy.current(communicationId, now);
            VerifiedGuardianEmail v = policy.verified(verificationId, c, now);
            EmailTemplates.Rendered rendered = policy.render(c);
            if (!policy.hash(c, v, rendered).equals(hash)) {
                throw ChildrenException.conflict("Review the current message and recipient before sending.");
            }
            Optional<DeliverySnapshot> previous = reader.forCommunication(communicationId);
            if (previous.isPresent()) {
                throw ChildrenException.conflict(
                    "This communication already has a delivery record. Review that record before any further action.");
            }
            Instant at = scheduled == null ? now : ChildrenRules.instant(scheduled);
            if (at.isBefore(now.minusSeconds(60)) || at.isAfter(now.plus(90, ChronoUnit.DAYS))) {
                throw ChildrenException.invalid("Schedule within the next 90 days.");
            }
            policy.schedule(c, at);
            if (!v.valid(at)) {
                throw ChildrenException.conflict("Email verification must cover the scheduled send time.");
            }
            UUID id = UUID.randomUUID();
            DeliverySnapshot d = new DeliverySnapshot(id, c.id(), v.id(), v.contactId(), v.email(), rendered.subject(),
                rendered.html(), hash, actor, now, at, at.isAfter(now) ? "SCHEDULED" : "QUEUED", 0,
                "<" + id + "@onechurch.delivery>", null, null, null, "", false, 0, "text/html", rendered.key(),
                rendered.version());
            deliveries.queue("children-ministry", d);
            audit.record(actor, "guardian.email.queued", id, "", now);
            return id;
        });
    }
    public void cancel(UUID actor, UUID id, long expected, String reason) {
        deliveries.cancel(actor, id, expected, reason);
    }
    public void retry(UUID actor, UUID id, long expected, String reason) {
        deliveries.retry(actor, id, expected, reason);
    }
    public void receipt(UUID actor, UUID id, String messageId, String key, String status, String reference) {
        deliveries.receipt(actor, id, messageId, key, status, reference);
    }
}
