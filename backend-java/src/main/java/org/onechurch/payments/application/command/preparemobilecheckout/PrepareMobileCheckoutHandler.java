package org.onechurch.payments.application.command.preparemobilecheckout;
import org.onechurch.identityaccess.application.contract.CurrentActor;
import org.onechurch.identityaccess.application.contract.TokenCodec;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.command.resumecheckout.ResumeCheckoutCommand;
import org.onechurch.payments.application.command.resumecheckout.ResumeCheckoutHandler;
import org.onechurch.payments.application.contract.CheckoutCodec;
import org.onechurch.payments.application.contract.MobileCheckoutOrigin;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.payments.application.contract.SubscriptionVault;
import org.onechurch.payments.domain.MobileCheckout;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.payments.domain.PaymentOrder;
import org.onechurch.payments.domain.repository.MobileCheckoutRepository;
import org.onechurch.payments.domain.repository.PaymentRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.time.Clock;
import java.time.Duration;
import java.time.Instant;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
public final class PrepareMobileCheckoutHandler {
    public record Handoff(String url, Instant expiresAt) {
        @Override
        public String toString() {
            return "Handoff[redacted]";
        }
    }
    private final PaymentAccess access;
    private final ResumeCheckoutHandler resume;
    private final PaymentRepository orders;
    private final MobileCheckoutRepository repository;
    private final CurrentActor actor;
    private final TokenCodec tokens;
    private final SubscriptionVault vault;
    private final MobileCheckoutOrigin origins;
    private final UnitOfWork tx;
    private final Clock clock;
    private final CheckoutCodec codec;
    public PrepareMobileCheckoutHandler(PaymentAccess access, ResumeCheckoutHandler resume, PaymentRepository orders,
        MobileCheckoutRepository repository, CurrentActor actor, TokenCodec tokens, SubscriptionVault vault,
        MobileCheckoutOrigin origins, UnitOfWork tx, Clock clock, CheckoutCodec codec) {
        this.access = access;
        this.resume = resume;
        this.orders = orders;
        this.repository = repository;
        this.actor = actor;
        this.tokens = tokens;
        this.vault = vault;
        this.origins = origins;
        this.tx = tx;
        this.clock = clock;
        this.codec = codec;
    }
    public Handoff handle(PrepareMobileCheckoutCommand c) {
        access.caller(c.actorId(), true);
        PaymentGateway.Checkout checkout = resume.handle(new ResumeCheckoutCommand(c.actorId(), c.orderId(), true));
        return tx.execute(() -> {
            PaymentOrder order = orders.lock(c.orderId()).orElseThrow(PaymentException::missing);
            access.order(c.actorId(), order, true, true);
            if (!List.of("PENDING", "READY").contains(order.status())
                || List.of("CANCEL_REQUESTED", "CANCELLED").contains(order.subscriptionStatus())) {
                throw PaymentException.conflict("Refresh this payment before opening checkout.");
            }
            String token = tokens.generate();
            Instant expires = clock.instant().plus(Duration.ofMinutes(5));
            Map<String, String> values = new LinkedHashMap<>(checkout.fields());
            values.put("__checkout_url", checkout.url());
            repository.insert(new MobileCheckout(tokens.hash(token), order.id(), c.actorId(),
                actor.current().orElseThrow().sessionId(), vault.encrypt(codec.encode(values)), expires, null));
            return new Handoff(
                origins.backend(order) + "/api/mobile-checkouts/" + URLEncoder.encode(token, StandardCharsets.UTF_8),
                expires);
        });
    }
}
