package org.onechurch.payments.infrastructure;
import org.onechurch.giving.application.contract.DonorCheckoutGuard;
import org.onechurch.identityaccess.application.contract.AccountDirectory;
import org.onechurch.identityaccess.application.contract.CurrentActor;
import org.onechurch.identityaccess.application.contract.SessionReader;
import org.onechurch.identityaccess.application.contract.TokenCodec;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.command.completemobilecheckout.CompleteMobileCheckoutHandler;
import org.onechurch.payments.application.command.preparemobilecheckout.PrepareMobileCheckoutHandler;
import org.onechurch.payments.application.command.resumecheckout.ResumeCheckoutHandler;
import org.onechurch.payments.application.contract.CheckoutCodec;
import org.onechurch.payments.application.contract.MobileCheckoutOrigin;
import org.onechurch.payments.application.contract.SubscriptionVault;
import org.onechurch.payments.domain.repository.MobileCheckoutRepository;
import org.onechurch.payments.domain.repository.PaymentRepository;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
@Configuration
public class MobileCheckoutConfiguration {
    @Bean
    PrepareMobileCheckoutHandler prepareMobileCheckout(PaymentAccess access, ResumeCheckoutHandler resume,
        PaymentRepository orders, MobileCheckoutRepository repository, CurrentActor actor, TokenCodec tokens,
        SubscriptionVault vault, MobileCheckoutOrigin origin, UnitOfWork tx, Clock clock, CheckoutCodec codec) {
        return new PrepareMobileCheckoutHandler(access, resume, orders, repository, actor, tokens, vault, origin, tx,
            clock, codec);
    }
    @Bean
    CompleteMobileCheckoutHandler completeMobileCheckout(MobileCheckoutRepository repository, PaymentRepository orders,
        SessionReader sessions, AccountDirectory accounts, DonorCheckoutGuard donors, TokenCodec tokens,
        SubscriptionVault vault, CheckoutCodec codec, UnitOfWork tx, Clock clock) {
        return new CompleteMobileCheckoutHandler(repository, orders, sessions, accounts, donors, tokens, vault, codec,
            tx, clock);
    }
}
