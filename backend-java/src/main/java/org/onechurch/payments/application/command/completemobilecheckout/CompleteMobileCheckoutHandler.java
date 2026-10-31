package org.onechurch.payments.application.command.completemobilecheckout;
import org.onechurch.giving.application.contract.DonorCheckoutGuard;
import org.onechurch.identityaccess.application.contract.AccountDirectory;
import org.onechurch.identityaccess.application.contract.SessionReader;
import org.onechurch.identityaccess.application.contract.TokenCodec;
import org.onechurch.payments.application.contract.CheckoutCodec;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.payments.application.contract.SubscriptionVault;
import org.onechurch.payments.domain.MobileCheckout;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.PaymentOrder;
import org.onechurch.payments.domain.repository.MobileCheckoutRepository;
import org.onechurch.payments.domain.repository.PaymentRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
/**
 * Trusted public entry point authenticated by a one-use, expiring capability bound to a live session and donor link.
 */
public final class CompleteMobileCheckoutHandler {
    private final MobileCheckoutRepository repository;
    private final PaymentRepository orders;
    private final SessionReader sessions;
    private final AccountDirectory accounts;
    private final DonorCheckoutGuard donors;
    private final TokenCodec tokens;
    private final SubscriptionVault vault;
    private final CheckoutCodec codec;
    private final UnitOfWork tx;
    private final Clock clock;
    public CompleteMobileCheckoutHandler(MobileCheckoutRepository repository, PaymentRepository orders,
        SessionReader sessions, AccountDirectory accounts, DonorCheckoutGuard donors, TokenCodec tokens,
        SubscriptionVault vault, CheckoutCodec codec, UnitOfWork tx, Clock clock) {
        this.repository = repository;
        this.orders = orders;
        this.sessions = sessions;
        this.accounts = accounts;
        this.donors = donors;
        this.tokens = tokens;
        this.vault = vault;
        this.codec = codec;
        this.tx = tx;
        this.clock = clock;
    }
    public PaymentGateway.Checkout handle(CompleteMobileCheckoutCommand c) {
        if (c.token() == null || !c.token().matches("[A-Za-z0-9_-]{20,200}")) {
            throw PaymentException.missing();
        }
        return tx.execute(() -> {
            MobileCheckout link = repository.lock(tokens.hash(c.token()))
                .filter(value -> value.usedAt() == null && value.expiresAt().isAfter(clock.instant()))
                .orElseThrow(PaymentException::missing);
            PaymentOrder order = orders.lock(link.orderId()).orElseThrow(PaymentException::missing);
            if (!accounts.active(link.accountId()) || sessions.findById(link.sessionId())
                .filter(s -> s.accountId().equals(link.accountId()) && s.expiresAt().isAfter(clock.instant()))
                .isEmpty()) {
                throw PaymentException.missing();
            }
            if (!donors.lockEligible(link.accountId(), order.donorId())) {
                throw PaymentException.missing();
            }
            if (!List.of("PENDING", "READY").contains(order.status())
                || List.of("CANCEL_REQUESTED", "CANCELLED").contains(order.subscriptionStatus())) {
                throw PaymentException
                    .conflict("This checkout is no longer awaiting payment. Return to the app and refresh.");
            }
            Map<String, String> values = new LinkedHashMap<>(codec.decode(vault.decrypt(link.encryptedCheckout())));
            String url = values.remove("__checkout_url");
            repository.used(link.tokenHash(), clock.instant());
            orders.audit(order.id(), "MOBILE_CHECKOUT_OPENED",
                "One-time browser handoff opened; payment is not confirmed.", link.accountId(), clock.instant());
            return new PaymentGateway.Checkout(order.checkoutId(), url, Map.copyOf(values));
        });
    }
}
