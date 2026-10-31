package org.onechurch.payments.application;
import org.onechurch.giving.application.contract.OnlineGivingLedger;
import org.onechurch.payments.domain.Charge;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.PaymentOrder;
import org.onechurch.payments.domain.Refund;
import org.onechurch.payments.domain.repository.PaymentRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.List;
import java.util.UUID;
/** Posting is separate from durable verification. A failed ledger transaction leaves evidence available for retry. */
public final class PaymentPosting {
    private final PaymentRepository repository;
    private final OnlineGivingLedger giving;
    private final UnitOfWork tx;
    private final Clock clock;
    public PaymentPosting(PaymentRepository repository, OnlineGivingLedger giving, UnitOfWork tx, Clock clock) {
        this.repository = repository;
        this.giving = giving;
        this.tx = tx;
        this.clock = clock;
    }
    public void post(UUID orderId) {
        post(orderId, () -> {
        });
    }
    public void post(UUID orderId, Runnable authorize) {
        try {
            tx.execute(() -> {
                PaymentOrder order = repository.lock(orderId).orElseThrow(PaymentException::missing);
                authorize.run();
                List<Charge> charges = order.mode().equals("live") ? repository.pendingCharges(orderId) : List.of();
                for (Charge charge : charges) {
                    if (charge.giftId() == null && order.mode().equals("live")) {
                        UUID gift = giving.post(charge.id(), order.donorId(), order.fundId(), charge.amountMinor(),
                            reference(order, charge.reference()), charge.receivedOn(), order.createdBy());
                        repository.posted(charge.id(), gift);
                    }
                }
                for (Refund refund : repository.pendingRefunds(orderId)) {
                    List<Charge> matches = repository.matchingCharges(orderId, refund.chargeId());
                    if (matches.size() != 1) {
                        repository.review(orderId, "A verified refund needs an explicit matching charge.");
                        continue;
                    }
                    Charge charge = matches.getFirst();
                    long refunded = repository.refunded(charge.id());
                    if (refund.amountMinor() > charge.amountMinor() - refunded) {
                        repository.review(orderId, "A refund exceeds the unrefunded charge. Review provider evidence.");
                        continue;
                    }
                    if (refund.refundedOn().isBefore(charge.receivedOn())) {
                        repository.review(orderId, "Refund date precedes the payment date; review provider evidence.");
                        continue;
                    }
                    if (order.mode().equals("live")) {
                        if (charge.giftId() == null) {
                            throw PaymentException.conflict("Verified payment still needs ledger posting.");
                        }
                        giving.refund(refund.id(), charge.giftId(), refund.amountMinor(),
                            reference(order, refund.reference()), refund.refundedOn(),
                            refund.createdBy() == null ? order.createdBy() : refund.createdBy());
                    }
                    repository.applied(refund.id(), charge.id());
                    if (order.mode().equals("live")) {
                        repository.review(orderId, "Refund changed the net settlement. Review the bank match.");
                    }
                    repository.audit(orderId, "REFUND_APPLIED", refund.reference(), refund.createdBy(),
                        clock.instant());
                }
                if (order.reviewReason() != null
                    && (order.reviewReason().startsWith("Verified financial evidence needs ledger posting.")
                        || order.reviewReason().equals("Verified financial evidence still needs matching or posting."))
                    && repository.pendingCharges(orderId).isEmpty() && repository.pendingRefunds(orderId).isEmpty()) {
                    boolean unmatched = repository.charges(orderId).stream().anyMatch(charge -> {
                        org.onechurch.payments.domain.SettlementReview review =
                            repository.latestReview(charge.id()).orElse(null);
                        long refunded = repository.refunded(charge.id());
                        return review == null || review.refundedMinor() != refunded
                            || charge.amountMinor() - refunded - review.feeMinor() != review.bankMinor();
                    });
                    repository.review(orderId, unmatched ? "Awaiting settlement review." : null);
                }
                return null;
            });
        } catch (RuntimeException error) {
            tx.execute(() -> {
                repository.lock(orderId).orElseThrow(PaymentException::missing);
                authorize.run();
                repository.review(orderId,
                    "Verified financial evidence needs ledger posting. Use Retry Giving posting to retry; do not collect payment again.");
                repository.audit(orderId, "POSTING_REVIEW", "Ledger posting failed; verified evidence retained.", null,
                    clock.instant());
                return null;
            });
        }
    }
    public static String reference(PaymentOrder order, String reference) {
        return order.provider() + ":" + order.mode() + ":" + reference;
    }
}
