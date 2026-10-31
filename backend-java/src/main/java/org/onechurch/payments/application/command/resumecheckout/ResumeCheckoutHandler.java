package org.onechurch.payments.application.command.resumecheckout;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.contract.CheckoutCodec;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.PaymentOrder;
import org.onechurch.payments.domain.repository.PaymentRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
public final class ResumeCheckoutHandler {
    private final PaymentAccess access;
    private final PaymentRepository repository;
    private final UnitOfWork tx;
    private final Clock clock;
    private final PaymentGateway gateway;
    private final PersonDirectory people;
    private final PersonEmailDirectory emails;
    private final CheckoutCodec codec;
    public ResumeCheckoutHandler(PaymentAccess access, PaymentRepository repository, UnitOfWork tx, Clock clock,
        PaymentGateway gateway, PersonDirectory people, PersonEmailDirectory emails, CheckoutCodec codec) {
        this.access = access;
        this.repository = repository;
        this.tx = tx;
        this.clock = clock;
        this.gateway = gateway;
        this.people = people;
        this.emails = emails;
        this.codec = codec;
    }
    public PaymentGateway.Checkout handle(ResumeCheckoutCommand c) {
        access.caller(c.actorId(), c.selfService());
        PaymentOrder order = repository.find(c.orderId()).orElseThrow(PaymentException::missing);
        access.order(c.actorId(), order, c.selfService(), false);
        if (!java.util.List.of("PENDING", "READY").contains(order.status())) {
            throw PaymentException.conflict("This payment already finished. Refresh its verified history.");
        }
        if (java.util.List.of("CANCEL_REQUESTED", "CANCELLED").contains(order.subscriptionStatus())) {
            throw PaymentException.conflict(
                "Monthly giving cancellation is requested. Refresh the subscription instead of resuming checkout.");
        }
        gateway.validateOrder(order);
        if (order.checkoutUrl() != null) {
            return new PaymentGateway.Checkout(order.checkoutId(), order.checkoutUrl(),
                codec.decode(order.checkoutFields()));
        }
        String name =
            people.get(order.donorId()).map(PersonDirectory.Entry::name).orElseThrow(PaymentException::missing);
        String email =
            emails.active(order.donorId()).stream().findFirst().map(PersonEmailDirectory.Email::value).orElse("");
        PaymentGateway.Checkout checkout;
        try {
            checkout = gateway.checkout(order, name, email);
        } catch (RuntimeException error) {
            tx.execute(() -> {
                PaymentOrder current = repository.lock(order.id()).orElseThrow(PaymentException::missing);
                access.order(c.actorId(), current, c.selfService(), true);
                repository.review(order.id(),
                    "Checkout creation is unconfirmed. Retry this same request; check the provider before creating another.");
                return null;
            });
            throw error;
        }
        return tx.execute(() -> {
            PaymentOrder current = repository.lock(order.id()).orElseThrow(PaymentException::missing);
            access.order(c.actorId(), current, c.selfService(), true);
            if (!java.util.List.of("PENDING", "READY").contains(current.status())) {
                throw PaymentException.conflict("Payment confirmation arrived. Refresh its history.");
            }
            if (java.util.List.of("CANCEL_REQUESTED", "CANCELLED").contains(current.subscriptionStatus())) {
                throw PaymentException
                    .conflict("Monthly giving cancellation was requested while checkout was being created.");
            }
            if (current.checkoutUrl() != null) {
                return new PaymentGateway.Checkout(current.checkoutId(), current.checkoutUrl(),
                    codec.decode(current.checkoutFields()));
            }
            repository.ready(order.id(), checkout.id(), checkout.url(), codec.encode(checkout.fields()));
            repository.review(order.id(), null);
            repository.audit(order.id(), "CHECKOUT_READY",
                "Hosted checkout confirmed; awaiting provider payment verification.", c.actorId(), clock.instant());
            return checkout;
        });
    }
}
