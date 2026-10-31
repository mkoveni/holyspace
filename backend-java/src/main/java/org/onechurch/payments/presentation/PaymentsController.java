package org.onechurch.payments.presentation;
import org.onechurch.payments.application.command.managesubscription.ManageSubscriptionCommand;
import org.onechurch.payments.application.command.managesubscription.ManageSubscriptionHandler;
import org.onechurch.payments.application.command.reconcilepayment.ReconcilePaymentCommand;
import org.onechurch.payments.application.command.reconcilepayment.ReconcilePaymentHandler;
import org.onechurch.payments.application.command.recordrefund.RecordRefundCommand;
import org.onechurch.payments.application.command.recordrefund.RecordRefundHandler;
import org.onechurch.payments.application.command.resumecheckout.ResumeCheckoutCommand;
import org.onechurch.payments.application.command.resumecheckout.ResumeCheckoutHandler;
import org.onechurch.payments.application.command.startpayment.StartPaymentCommand;
import org.onechurch.payments.application.command.startpayment.StartPaymentHandler;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.payments.application.contract.PaymentReader;
import org.onechurch.payments.application.query.getpayment.GetPaymentHandler;
import org.onechurch.payments.application.query.getpayment.GetPaymentQuery;
import org.onechurch.payments.application.query.listpaymentproviders.ListPaymentProvidersHandler;
import org.onechurch.payments.application.query.listpaymentproviders.ListPaymentProvidersQuery;
import org.onechurch.payments.application.query.listpayments.ListPaymentsHandler;
import org.onechurch.payments.application.query.listpayments.ListPaymentsQuery;
import org.onechurch.payments.presentation.api.PaymentsApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.UUID;
@RestController
public class PaymentsController implements PaymentsApi {
    private final StartPaymentHandler start;
    private final ResumeCheckoutHandler resume;
    private final RecordRefundHandler refund;
    private final ReconcilePaymentHandler review;
    private final ManageSubscriptionHandler subscription;
    private final ListPaymentsHandler list;
    private final GetPaymentHandler get;
    private final ListPaymentProvidersHandler providers;
    public PaymentsController(StartPaymentHandler start, ResumeCheckoutHandler resume, RecordRefundHandler refund,
        ReconcilePaymentHandler review, ManageSubscriptionHandler subscription, ListPaymentsHandler list,
        GetPaymentHandler get, ListPaymentProvidersHandler providers) {
        this.start = start;
        this.resume = resume;
        this.refund = refund;
        this.review = review;
        this.subscription = subscription;
        this.list = list;
        this.get = get;
        this.providers = providers;
    }
    @Override
    @PreAuthorize("hasAuthority('payments.manage')")
    public List<PaymentGateway.Provider> providers() {
        return providers.handle(new ListPaymentProvidersQuery());
    }
    @Override
    @PreAuthorize("hasAuthority('payments.manage')")
    public PaymentReader.Page list(int limit, int offset, boolean reviewOnly) {
        return list.handle(new ListPaymentsQuery(limit, offset, reviewOnly));
    }
    @Override
    @PreAuthorize("hasAuthority('payments.manage')")
    public PaymentReader.Detail detail(UUID id) {
        return get.handle(new GetPaymentQuery(id));
    }
    @Override
    @PreAuthorize("hasAuthority('payments.manage')")
    public Created start(StartBody b, OAuth2AuthenticatedPrincipal actor) {
        return new Created(
            start.handle(new StartPaymentCommand(UUID.fromString(actor.getName()), b.requestId(), b.donorId(),
                b.fundId(), b.amountMinor().longValueExact(), b.provider(), Boolean.TRUE.equals(b.recurring()))));
    }
    @Override
    @PreAuthorize("hasAuthority('payments.manage')")
    public PaymentGateway.Checkout checkout(UUID id, OAuth2AuthenticatedPrincipal actor) {
        return resume.handle(new ResumeCheckoutCommand(UUID.fromString(actor.getName()), id));
    }
    @Override
    @PreAuthorize("hasAuthority('payments.manage') and hasAuthority('payments.refund')")
    public void refund(UUID id, RefundBody b, OAuth2AuthenticatedPrincipal actor) {
        refund.handle(new RecordRefundCommand(UUID.fromString(actor.getName()), id, b.chargeId(), b.providerReference(),
            b.amountMinor().longValueExact(), b.refundedOn(), b.reason()));
    }
    @Override
    @PreAuthorize("hasAuthority('payments.manage') and hasAuthority('payments.reconcile')")
    public Created review(UUID id, ReviewBody b, OAuth2AuthenticatedPrincipal actor) {
        return new Created(review.handle(new ReconcilePaymentCommand(UUID.fromString(actor.getName()), b.requestId(),
            id, b.chargeId(), b.expectedVersion(), b.settlementReference(), b.feeMinor().longValueExact(),
            b.bankMinor().longValueExact(), b.note())));
    }
    @Override
    @PreAuthorize("hasAuthority('payments.manage')")
    public void subscription(UUID id, SubscriptionBody b, OAuth2AuthenticatedPrincipal actor) {
        subscription.handle(
            new ManageSubscriptionCommand(UUID.fromString(actor.getName()), id, b.expectedVersion(), b.cancel()));
    }
}
