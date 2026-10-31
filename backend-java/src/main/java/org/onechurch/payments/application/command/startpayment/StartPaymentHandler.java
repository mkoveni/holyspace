package org.onechurch.payments.application.command.startpayment;
import org.onechurch.giving.application.contract.OnlineGivingLedger;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.PaymentHash;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.PaymentOrder;
import org.onechurch.payments.domain.repository.PaymentRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.UUID;
public final class StartPaymentHandler {
    private final PaymentAccess access;
    private final PaymentRepository repository;
    private final UnitOfWork tx;
    private final Clock clock;
    private final PaymentGateway gateway;
    private final OnlineGivingLedger giving;
    private final org.onechurch.payments.application.contract.PaymentConfigurationBinding configurations;
    public StartPaymentHandler(PaymentAccess access, PaymentRepository repository, UnitOfWork tx, Clock clock,
        PaymentGateway gateway, OnlineGivingLedger giving,
        org.onechurch.payments.application.contract.PaymentConfigurationBinding configurations) {
        this.access = access;
        this.repository = repository;
        this.tx = tx;
        this.clock = clock;
        this.gateway = gateway;
        this.giving = giving;
        this.configurations = configurations;
    }
    public UUID handle(StartPaymentCommand c) {
        access.caller(c.actorId(), c.selfService());
        return tx.execute(() -> {
            PaymentOrder prior = repository.find(c.requestId()).orElse(null);
            String mode = prior == null ? gateway.mode(c.provider()) : prior.mode();
            String hash = PaymentHash.of(c.selfService() ? "donor-checkout" : "checkout", c.actorId(), c.donorId(),
                c.fundId(), c.amountMinor(), c.provider(), mode, c.recurring());
            if (prior != null) {
                access.order(c.actorId(), prior, c.selfService(), true);
                if (!prior.requestHash().equals(hash)) {
                    throw PaymentException.conflict("This request identifier was used for different payment details.");
                }
                return prior.id();
            }
            UUID configurationId = configurations.bind(c.provider(), mode, c.recurring());
            access.start(c.actorId(), c.donorId(), c.fundId(), c.selfService(), true);
            giving.validate(c.donorId(), c.fundId());
            access.start(c.actorId(), c.donorId(), c.fundId(), c.selfService(), false);
            PaymentOrder order = new PaymentOrder(c.requestId(), c.donorId(), c.fundId(), c.amountMinor(), c.provider(),
                mode, c.recurring(), "PENDING", null, null, null, c.recurring() ? "PENDING" : "NONE", null, null, null,
                0, hash, c.actorId(), clock.instant(), configurationId, c.selfService());
            if (!repository.insert(order)) {
                PaymentOrder existing = repository.find(order.id())
                    .orElseThrow(() -> PaymentException.conflict(
                        "A monthly arrangement is already active or awaiting confirmation for this donor and fund."));
                if (!existing.requestHash().equals(hash)) {
                    throw PaymentException.conflict("This request identifier was already used.");
                }
                return existing.id();
            }
            repository.audit(order.id(), "CHECKOUT_REQUESTED", "Hosted checkout requested; no gift posted.",
                c.actorId(), clock.instant());
            return order.id();
        });
    }
}
