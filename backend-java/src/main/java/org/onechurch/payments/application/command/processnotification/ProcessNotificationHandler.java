package org.onechurch.payments.application.command.processnotification;
import org.onechurch.payments.application.PaymentHash;
import org.onechurch.payments.application.PaymentPosting;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.payments.application.contract.SubscriptionVault;
import org.onechurch.payments.domain.Charge;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.PaymentOrder;
import org.onechurch.payments.domain.Refund;
import org.onechurch.payments.domain.repository.PaymentRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.LocalDate;
import java.util.Optional;
import java.util.UUID;
/** Public provider entry point. Only a successfully authenticated gateway notification enters a write transaction. */
public final class ProcessNotificationHandler {
    private final PaymentRepository repository;
    private final UnitOfWork tx;
    private final Clock clock;
    private final PaymentGateway gateway;
    private final SubscriptionVault vault;
    private final PaymentPosting posting;
    public ProcessNotificationHandler(PaymentRepository repository, UnitOfWork tx, Clock clock, PaymentGateway gateway,
        SubscriptionVault vault, PaymentPosting posting) {
        this.repository = repository;
        this.tx = tx;
        this.clock = clock;
        this.gateway = gateway;
        this.vault = vault;
        this.posting = posting;
    }
    public void handle(ProcessNotificationCommand c) {
        PaymentGateway.Notification event = gateway.verify(c.provider(), c.body(), c.headers(), c.ip());
        if (event == null) {
            return;
        }
        UUID id = resolve(event);
        if (id == null) {
            return;
        }
        tx.execute(() -> {
            PaymentOrder order = repository.lock(id).orElseThrow(PaymentException::missing);
            if (!order.provider().equals(event.provider()) || !order.mode().equals(event.mode())) {
                throw PaymentException.invalid("Provider environment does not match the payment.");
            }
            if (event.provider().equals("yoco")
                && (order.checkoutId() == null || !order.checkoutId().equals(event.checkoutId()))) {
                throw PaymentException.unavailable("Checkout confirmation is pending; retry this notification.");
            }
            String hash = PaymentHash.of(event.provider(), event.mode(), event.type(), event.reference(),
                event.checkoutId(), event.orderId(), event.amountMinor(), event.currency(),
                event.token() == null ? null : PaymentHash.of(event.token()), event.date());
            if (!repository.remember(event.provider(), event.mode(), event.eventId(), id, hash, clock.instant())) {
                if (!repository.notification(event.provider(), event.mode(), event.eventId())
                    .orElseThrow()
                    .equals(hash)) {
                    repository.review(id, "A repeated notification has conflicting financial details.");
                }
                return null;
            }
            if (!"ZAR".equals(event.currency()) || (!event.type().equals("REFUNDED")
                && !event.type().equals("REFUND_FAILED") && event.amountMinor() != order.amountMinor())) {
                repository.review(id, "Verified amount or currency does not match the checkout; no gift posted.");
                return null;
            }
            if (event.token() != null) {
                UUID tokenOwner =
                    repository.lookup(event.provider(), event.mode(), null, PaymentHash.of(event.token())).orElse(id);
                if (!tokenOwner.equals(id)) {
                    repository.review(id, "Subscription identity belongs to another payment arrangement.");
                    return null;
                }
            }
            if (event.token() != null && order.tokenHash() != null
                && !PaymentHash.of(event.token()).equals(order.tokenHash())) {
                repository.review(id, "Subscription token conflicts with the original arrangement.");
                return null;
            }
            if (event.type().equals("PAID")) {
                paid(order, event);
            } else if (event.type().equals("REFUNDED")) {
                refund(order, event);
            } else if (event.type().equals("FAILED")) {
                if (repository.charges(id).isEmpty()) {
                    repository.status(id, "FAILED");
                }
                if (order.recurring()) {
                    repository.review(id, "A monthly charge failed; review the provider subscription.");
                }
            } else {
                repository.review(id, "The provider reported a failed refund. Check its dashboard.");
            }
            repository.audit(id, "PROVIDER_" + event.type(), event.reference(), null, clock.instant());
            return null;
        });
        posting.post(id);
    }
    private UUID resolve(PaymentGateway.Notification e) {
        if (e.provider().equals("yoco")) {
            return repository.lookup(e.provider(), e.mode(), e.checkoutId(), null).orElseGet(() -> {
                if (e.orderId() != null && repository.find(e.orderId()).isPresent()) {
                    throw PaymentException.unavailable("Checkout confirmation is pending; retry this notification.");
                }
                return null;
            });
        }
        if (e.orderId() != null && repository.find(e.orderId()).isPresent()) {
            return e.orderId();
        }
        return e.token() == null ? null
            : repository.lookup(e.provider(), e.mode(), null, PaymentHash.of(e.token())).orElse(null);
    }
    private void paid(PaymentOrder order, PaymentGateway.Notification e) {
        Optional<Charge> prior = repository.charge(e.provider(), e.mode(), e.reference());
        if (prior.isPresent()) {
            Charge charge = prior.orElseThrow();
            if (!charge.orderId().equals(order.id()) || charge.amountMinor() != e.amountMinor()) {
                repository.review(order.id(), "Provider charge reference conflicts with another payment.");
            }
            return;
        }
        if (!order.recurring() && !repository.charges(order.id()).isEmpty()) {
            repository.review(order.id(),
                "More than one charge was collected for a one-off checkout. Review possible duplicate collection.");
        }
        LocalDate date = e.date() == null ? LocalDate.now(clock) : e.date();
        Charge charge = new Charge(UUID.randomUUID(), order.id(), order.provider(), order.mode(), e.reference(),
            e.amountMinor(), date, null, clock.instant());
        if (!repository.insert(charge)) {
            Charge existing = repository.charge(e.provider(), e.mode(), e.reference()).orElseThrow();
            if (!existing.orderId().equals(order.id()) || existing.amountMinor() != charge.amountMinor()) {
                repository.review(order.id(), "Provider charge reference conflicts with another payment.");
            }
            return;
        }
        repository.status(order.id(), "PAID");
        repository.review(order.id(), order.mode().equals("live") ? "Awaiting settlement review." : null);
        if (order.recurring()) {
            if (e.token() == null) {
                repository.review(order.id(),
                    "Monthly charge arrived without a subscription token. Review the provider.");
            } else {
                String status = java.util.List.of("CANCEL_REQUESTED", "CANCELLED").contains(order.subscriptionStatus())
                    ? order.subscriptionStatus() : "ACTIVE";
                repository.subscription(order.id(), status, vault.encrypt(e.token()), PaymentHash.of(e.token()));
            }
        }
    }
    private void refund(PaymentOrder order, PaymentGateway.Notification e) {
        Refund refund = new Refund(UUID.randomUUID(), order.id(), null, order.provider(), order.mode(), e.reference(),
            e.amountMinor(), e.date() == null ? LocalDate.now(clock) : e.date(), false, "Authenticated provider refund",
            null, clock.instant());
        if (!repository.insert(refund)) {
            Refund prior = repository.refund(order.provider(), order.mode(), e.reference()).orElseThrow();
            if (!prior.orderId().equals(order.id()) || prior.amountMinor() != e.amountMinor()) {
                repository.review(order.id(), "Provider refund reference conflicts with existing evidence.");
            }
        }
    }
}
