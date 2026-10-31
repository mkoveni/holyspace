package org.onechurch.payments.application.command.retrypaymentposting;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.PaymentPosting;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.PaymentOrder;
import org.onechurch.payments.domain.repository.PaymentRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
public final class RetryPaymentPostingHandler {
    public record Result(boolean complete, String message) {}
    private final PaymentAccess access;
    private final PaymentRepository repository;
    private final PaymentPosting posting;
    private final UnitOfWork tx;
    private final Clock clock;
    public RetryPaymentPostingHandler(PaymentAccess access, PaymentRepository repository, PaymentPosting posting,
        UnitOfWork tx, Clock clock) {
        this.access = access;
        this.repository = repository;
        this.posting = posting;
        this.tx = tx;
        this.clock = clock;
    }
    public Result handle(RetryPaymentPostingCommand c) {
        access.manage(c.actorId());
        tx.execute(() -> {
            PaymentOrder order = repository.lock(c.orderId()).orElseThrow(PaymentException::missing);
            access.manage(c.actorId());
            order.expected(c.expectedVersion());
            if (!order.mode().equals("live") || repository.charges(order.id()).isEmpty()) {
                throw PaymentException.conflict("Only a verified live payment can be posted to Giving.");
            }
            return null;
        });
        posting.post(c.orderId(), () -> access.manage(c.actorId()));
        return tx.execute(() -> {
            repository.lock(c.orderId()).orElseThrow(PaymentException::missing);
            access.manage(c.actorId());
            boolean complete =
                repository.pendingCharges(c.orderId()).isEmpty() && repository.pendingRefunds(c.orderId()).isEmpty();
            repository.audit(c.orderId(), "POSTING_RETRIED", complete ? "Verified evidence posted to Giving."
                : "Posting still needs attention; do not collect payment again.", c.actorId(), clock.instant());
            return new Result(complete, complete ? "Contribution recorded in Giving."
                : "Posting could not be completed. Verified payment evidence is retained; do not collect payment again.");
        });
    }
}
