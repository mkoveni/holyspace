package org.onechurch.payments.application.command.recordrefund;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.PaymentPosting;
import org.onechurch.payments.domain.Charge;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.PaymentOrder;
import org.onechurch.payments.domain.Refund;
import org.onechurch.payments.domain.repository.PaymentRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.LocalDate;
import java.util.UUID;
public final class RecordRefundHandler {
    private final PaymentAccess access;
    private final PaymentRepository repository;
    private final UnitOfWork tx;
    private final Clock clock;
    private final PaymentPosting posting;
    public RecordRefundHandler(PaymentAccess access, PaymentRepository repository, UnitOfWork tx, Clock clock,
        PaymentPosting posting) {
        this.access = access;
        this.repository = repository;
        this.tx = tx;
        this.clock = clock;
        this.posting = posting;
    }
    public void handle(RecordRefundCommand c) {
        access.refund(c.actorId());
        String reference = PaymentOrder.text(c.providerReference(), 150, "Provider refund reference"),
            reason = PaymentOrder.text(c.reason(), 1000, "Completed refund evidence");
        if (c.amountMinor() < 1 || c.amountMinor() > 100000000 || c.refundedOn() == null
            || c.refundedOn().isAfter(LocalDate.now(clock))) {
            throw PaymentException.invalid("Enter the completed refund amount and date.");
        }
        tx.execute(() -> {
            PaymentOrder order = repository.lock(c.orderId()).orElseThrow(PaymentException::missing);
            access.refund(c.actorId());
            Charge charge = repository.matchingCharges(order.id(), c.chargeId())
                .stream()
                .findFirst()
                .orElseThrow(PaymentException::missing);
            Refund prior = repository.refund(order.provider(), order.mode(), reference).orElse(null);
            if (prior != null) {
                if (!prior.orderId().equals(order.id()) || prior.amountMinor() != c.amountMinor()
                    || !prior.refundedOn().equals(c.refundedOn())
                    || (prior.chargeId() != null && !prior.chargeId().equals(charge.id()))) {
                    throw PaymentException.conflict("Refund reference conflicts with existing evidence.");
                }
                if (prior.chargeId() == null) {
                    repository.matchRefund(prior.id(), charge.id());
                }
                return null;
            }
            if (order.provider().equals("yoco")) {
                throw PaymentException.conflict("Await the authenticated Yoco refund notification before matching it.");
            }
            if (c.refundedOn().isBefore(charge.receivedOn())
                || c.amountMinor() > charge.amountMinor() - repository.refunded(charge.id())) {
                throw PaymentException.conflict("Refund exceeds the remaining charge or precedes its payment date.");
            }
            Refund refund = new Refund(UUID.randomUUID(), order.id(), charge.id(), order.provider(), order.mode(),
                reference, c.amountMinor(), c.refundedOn(), false, reason, c.actorId(), clock.instant());
            if (!repository.insert(refund)) {
                throw PaymentException.conflict("Refund reference was recorded concurrently. Refresh before retrying.");
            }
            repository.audit(order.id(), "REFUND_CONFIRMED", reference + ": " + reason, c.actorId(), clock.instant());
            return null;
        });
        posting.post(c.orderId(), () -> access.refund(c.actorId()));
    }
}
