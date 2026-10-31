package org.onechurch.payments.infrastructure;
import org.onechurch.giving.application.contract.DonorIdentity;
import org.onechurch.giving.application.contract.DonorIdentityGuard;
import org.onechurch.giving.application.contract.OnlineFundDirectory;
import org.onechurch.giving.application.contract.OnlineGivingLedger;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.payments.application.PaymentAccess;
import org.onechurch.payments.application.PaymentPosting;
import org.onechurch.payments.application.command.managesubscription.ManageSubscriptionHandler;
import org.onechurch.payments.application.command.processnotification.ProcessNotificationHandler;
import org.onechurch.payments.application.command.reconcilepayment.ReconcilePaymentHandler;
import org.onechurch.payments.application.command.recordrefund.RecordRefundHandler;
import org.onechurch.payments.application.command.resumecheckout.ResumeCheckoutHandler;
import org.onechurch.payments.application.command.retrypaymentposting.RetryPaymentPostingHandler;
import org.onechurch.payments.application.command.startpayment.StartPaymentHandler;
import org.onechurch.payments.application.contract.CheckoutCodec;
import org.onechurch.payments.application.contract.PaymentConfigurationBinding;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.payments.application.contract.PaymentReader;
import org.onechurch.payments.application.contract.SubscriptionVault;
import org.onechurch.payments.application.query.getpayment.GetPaymentHandler;
import org.onechurch.payments.application.query.listpaymentproviders.ListPaymentProvidersHandler;
import org.onechurch.payments.application.query.listpayments.ListPaymentsHandler;
import org.onechurch.payments.domain.repository.PaymentRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.onechurch.shared.application.UnitOfWork;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.Clock;
import java.time.ZoneId;
@Configuration
public class PaymentConfiguration {
    @Bean
    RetryPaymentPostingHandler retryPaymentPostingHandler(PaymentAccess access, PaymentRepository repository,
        PaymentPosting posting, UnitOfWork tx, Clock clock) {
        return new RetryPaymentPostingHandler(access, repository, posting, tx, clock);
    }
    @Bean
    PaymentAccess paymentAccess(PermissionChecker auth, DonorIdentity donors, DonorIdentityGuard guard,
        OnlineFundDirectory funds) {
        return new PaymentAccess(auth, donors, guard, funds);
    }
    @Bean
    PaymentPosting paymentPosting(PaymentRepository repository, OnlineGivingLedger giving, UnitOfWork tx, Clock clock,
        ZoneId zone) {
        return new PaymentPosting(repository, giving, tx, clock.withZone(zone));
    }
    @Bean
    StartPaymentHandler startPaymentHandler(PaymentAccess access, PaymentRepository repository, UnitOfWork tx,
        Clock clock, ZoneId zone, PaymentGateway gateway, OnlineGivingLedger giving,
        PaymentConfigurationBinding configurations) {
        return new StartPaymentHandler(access, repository, tx, clock.withZone(zone), gateway, giving, configurations);
    }
    @Bean
    ResumeCheckoutHandler resumeCheckoutHandler(PaymentAccess access, PaymentRepository repository, UnitOfWork tx,
        Clock clock, ZoneId zone, PaymentGateway gateway, PersonDirectory people, PersonEmailDirectory emails,
        CheckoutCodec codec) {
        return new ResumeCheckoutHandler(access, repository, tx, clock.withZone(zone), gateway, people, emails, codec);
    }
    @Bean
    ProcessNotificationHandler processNotificationHandler(PaymentRepository repository, UnitOfWork tx, Clock clock,
        ZoneId zone, PaymentGateway gateway, SubscriptionVault vault, PaymentPosting posting) {
        return new ProcessNotificationHandler(repository, tx, clock.withZone(zone), gateway, vault, posting);
    }
    @Bean
    RecordRefundHandler recordRefundHandler(PaymentAccess access, PaymentRepository repository, UnitOfWork tx,
        Clock clock, ZoneId zone, PaymentPosting posting) {
        return new RecordRefundHandler(access, repository, tx, clock.withZone(zone), posting);
    }
    @Bean
    ReconcilePaymentHandler reconcilePaymentHandler(PaymentAccess access, PaymentRepository repository, UnitOfWork tx,
        Clock clock, ZoneId zone, PaymentPosting posting) {
        return new ReconcilePaymentHandler(access, repository, tx, clock.withZone(zone), posting);
    }
    @Bean
    ManageSubscriptionHandler manageSubscriptionHandler(PaymentAccess access, PaymentRepository repository,
        UnitOfWork tx, Clock clock, ZoneId zone, PaymentGateway gateway, SubscriptionVault vault) {
        return new ManageSubscriptionHandler(access, repository, tx, clock.withZone(zone), gateway, vault);
    }
    @Bean
    ListPaymentsHandler listPaymentsHandler(PaymentAccess access, PaymentReader reader) {
        return new ListPaymentsHandler(access, reader);
    }
    @Bean
    GetPaymentHandler getPaymentHandler(PaymentAccess access, PaymentReader reader) {
        return new GetPaymentHandler(access, reader);
    }
    @Bean
    ListPaymentProvidersHandler listPaymentProvidersHandler(PaymentAccess access, PaymentGateway gateway) {
        return new ListPaymentProvidersHandler(access, gateway);
    }
}
