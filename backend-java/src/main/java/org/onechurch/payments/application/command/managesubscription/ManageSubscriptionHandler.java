package org.onechurch.payments.application.command.managesubscription;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.payments.application.contract.SubscriptionVault;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.PaymentOrder;
import org.onechurch.payments.domain.repository.PaymentRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
public final class ManageSubscriptionHandler {
    private final PaymentAccess access;
    private final PaymentRepository repository;
    private final UnitOfWork tx;
    private final Clock clock;
    private final PaymentGateway gateway;
    private final SubscriptionVault vault;
    public ManageSubscriptionHandler(PaymentAccess access, PaymentRepository repository, UnitOfWork tx, Clock clock,
        PaymentGateway gateway, SubscriptionVault vault) {
        this.access = access;
        this.repository = repository;
        this.tx = tx;
        this.clock = clock;
        this.gateway = gateway;
        this.vault = vault;
    }
    public void handle(ManageSubscriptionCommand c) {
        access.caller(c.actorId(), c.selfService());
        PaymentOrder order = tx.execute(() -> {
            PaymentOrder current = repository.lock(c.orderId()).orElseThrow(PaymentException::missing);
            access.order(c.actorId(), current, c.selfService(), true);
            current.expected(c.expectedVersion());
            if (!current.recurring()) {
                throw PaymentException.invalid("This is a one-off checkout.");
            }
            if (c.cancel() && !current.subscriptionStatus().equals("CANCELLED")) {
                repository.subscription(current.id(), "CANCEL_REQUESTED", current.encryptedToken(),
                    current.tokenHash());
                repository.audit(current.id(), "CANCELLATION_REQUESTED", "Awaiting provider confirmation.", c.actorId(),
                    clock.instant());
            }
            return current;
        });
        if (order.subscriptionStatus().equals("CANCELLED")) {
            return;
        }
        if (order.encryptedToken() == null) {
            throw PaymentException.conflict(
                "Subscription token is not confirmed yet. Cancellation remains requested; retry after provider confirmation.");
        }
        String status = gateway.subscription(order, vault.decrypt(order.encryptedToken()), c.cancel());
        tx.execute(() -> {
            PaymentOrder current = repository.lock(order.id()).orElseThrow(PaymentException::missing);
            access.order(c.actorId(), current, c.selfService(), true);
            if (!current.subscriptionStatus().equals("CANCELLED")) {
                String result = !c.cancel() && current.subscriptionStatus().equals("CANCEL_REQUESTED")
                    && !status.equals("CANCELLED") ? "CANCEL_REQUESTED" : status;
                repository.subscription(order.id(), result, current.encryptedToken(), current.tokenHash());
                repository.audit(order.id(), "SUBSCRIPTION_CONFIRMED", result, c.actorId(), clock.instant());
            }
            return null;
        });
    }
}
