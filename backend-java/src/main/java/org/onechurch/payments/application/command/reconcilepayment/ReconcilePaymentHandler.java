package org.onechurch.payments.application.command.reconcilepayment;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.PaymentHash;
import org.onechurch.payments.application.PaymentPosting;
import org.onechurch.payments.domain.Charge;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.PaymentOrder;
import org.onechurch.payments.domain.SettlementReview;
import org.onechurch.payments.domain.repository.PaymentRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.UUID;
public final class ReconcilePaymentHandler {
    private final PaymentAccess access;
    private final PaymentRepository repository;
    private final UnitOfWork tx;
    private final Clock clock;
    private final PaymentPosting posting;
    public ReconcilePaymentHandler(PaymentAccess access, PaymentRepository repository, UnitOfWork tx, Clock clock,
        PaymentPosting posting) {
        this.access = access;
        this.repository = repository;
        this.tx = tx;
        this.clock = clock;
        this.posting = posting;
    }
    public UUID handle(ReconcilePaymentCommand c) {
        access.reconcile(c.actorId());
        if (repository.find(c.orderId()).orElseThrow(PaymentException::missing).mode().equals("test")) {
            throw PaymentException.conflict("Simulation payments have no real bank settlement to review.");
        }
        posting.post(c.orderId(), () -> access.reconcile(c.actorId()));
        String reference = PaymentOrder.text(c.settlementReference(), 200, "Settlement reference"),
            note = PaymentOrder.text(c.note(), 1000, "Review evidence");
        String hash = PaymentHash.of("settlement", c.actorId(), c.orderId(), c.chargeId(), c.expectedVersion(),
            reference, c.feeMinor(), c.bankMinor(), note);
        return tx.execute(() -> {
            PaymentOrder order = repository.lock(c.orderId()).orElseThrow(PaymentException::missing);
            access.reconcile(c.actorId());
            SettlementReview replay = repository.reviewRequest(c.requestId()).orElse(null);
            if (replay != null) {
                if (!replay.requestHash().equals(hash)) {
                    throw PaymentException.conflict("Review request identifier was already used.");
                }
                return replay.id();
            }
            Charge charge = repository.matchingCharges(order.id(), c.chargeId())
                .stream()
                .findFirst()
                .orElseThrow(PaymentException::missing);
            long version = repository.latestReview(charge.id()).map(SettlementReview::version).orElse(0L);
            if (version != c.expectedVersion()) {
                throw PaymentException.conflict("Settlement review changed. Refresh before trying again.");
            }
            long refunded = repository.refunded(charge.id());
            SettlementReview review = new SettlementReview(c.requestId(), order.id(), charge.id(), version + 1,
                reference, c.feeMinor(), c.bankMinor(), refunded, note, c.actorId(), clock.instant(), hash);
            repository.insert(review);
            boolean pending = !repository.pendingRefunds(order.id()).isEmpty()
                || (order.mode().equals("live") && !repository.pendingCharges(order.id()).isEmpty());
            boolean discrepancy = repository.charges(order.id()).stream().anyMatch(ch -> {
                SettlementReview latest = repository.latestReview(ch.id()).orElse(null);
                long returned = repository.refunded(ch.id());
                return latest == null || latest.refundedMinor() != returned
                    || ch.amountMinor() - returned - latest.feeMinor() != latest.bankMinor();
            });
            repository.review(order.id(), pending ? "Verified financial evidence still needs matching or posting."
                : discrepancy ? "Bank amount does not match the charge less refunds and fees." : null);
            repository.audit(order.id(), "SETTLEMENT_REVIEWED", note, c.actorId(), clock.instant());
            return review.id();
        });
    }
}
