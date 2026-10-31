package org.onechurch.payments.presentation;
import org.onechurch.payments.application.command.managesubscription.ManageSubscriptionCommand;
import org.onechurch.payments.application.command.managesubscription.ManageSubscriptionHandler;
import org.onechurch.payments.application.command.resumecheckout.ResumeCheckoutCommand;
import org.onechurch.payments.application.command.resumecheckout.ResumeCheckoutHandler;
import org.onechurch.payments.application.command.startdonorpayment.StartDonorPaymentCommand;
import org.onechurch.payments.application.command.startdonorpayment.StartDonorPaymentHandler;
import org.onechurch.payments.application.contract.DonorPaymentReader;
import org.onechurch.payments.application.contract.PaymentGateway;
import org.onechurch.payments.application.query.getdonorpayment.GetDonorPaymentHandler;
import org.onechurch.payments.application.query.getdonorpayment.GetDonorPaymentQuery;
import org.onechurch.payments.application.query.getmygiving.GetMyGivingHandler;
import org.onechurch.payments.application.query.getmygiving.GetMyGivingQuery;
import org.onechurch.payments.presentation.api.DonorGivingApi;
import org.onechurch.payments.presentation.api.PaymentsApi;
import org.springframework.http.CacheControl;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
@PreAuthorize("isAuthenticated()")
public class DonorGivingController implements DonorGivingApi {
    private final GetMyGivingHandler overview;
    private final GetDonorPaymentHandler get;
    private final StartDonorPaymentHandler start;
    private final ResumeCheckoutHandler checkout;
    private final ManageSubscriptionHandler subscriptions;
    public DonorGivingController(GetMyGivingHandler overview, GetDonorPaymentHandler get,
        StartDonorPaymentHandler start, ResumeCheckoutHandler checkout, ManageSubscriptionHandler subscriptions) {
        this.overview = overview;
        this.get = get;
        this.start = start;
        this.checkout = checkout;
        this.subscriptions = subscriptions;
    }
    @Override
    public ResponseEntity<GetMyGivingHandler.Result> overview(int limit, int paymentOffset, int giftOffset) {
        return ResponseEntity.ok()
            .cacheControl(CacheControl.noStore())
            .body(overview.handle(new GetMyGivingQuery(limit, paymentOffset, giftOffset)));
    }
    @Override
    public ResponseEntity<DonorPaymentReader.Detail> detail(UUID orderId) {
        return ResponseEntity.ok()
            .cacheControl(CacheControl.noStore())
            .body(get.handle(new GetDonorPaymentQuery(orderId)));
    }
    @Override
    public PaymentsApi.Created start(StartBody b, OAuth2AuthenticatedPrincipal actor) {
        return new PaymentsApi.Created(start.handle(new StartDonorPaymentCommand(UUID.fromString(actor.getName()),
            b.requestId(), b.fundId(), b.amountMinor().longValueExact(), b.provider(), b.recurring())));
    }
    @Override
    public ResponseEntity<PaymentGateway.Checkout> checkout(UUID orderId, OAuth2AuthenticatedPrincipal actor) {
        return ResponseEntity.ok()
            .cacheControl(CacheControl.noStore())
            .body(checkout.handle(new ResumeCheckoutCommand(UUID.fromString(actor.getName()), orderId, true)));
    }
    @Override
    public void subscription(UUID orderId, PaymentsApi.SubscriptionBody b, OAuth2AuthenticatedPrincipal actor) {
        subscriptions.handle(new ManageSubscriptionCommand(UUID.fromString(actor.getName()), orderId,
            b.expectedVersion(), b.cancel(), true));
    }
}
