package org.onechurch.payments.presentation;
import org.onechurch.payments.application.command.approvepaymentreceipt.ApprovePaymentReceiptCommand;
import org.onechurch.payments.application.command.approvepaymentreceipt.ApprovePaymentReceiptHandler;
import org.onechurch.payments.application.command.retrypaymentreceipt.RetryPaymentReceiptCommand;
import org.onechurch.payments.application.command.retrypaymentreceipt.RetryPaymentReceiptHandler;
import org.onechurch.payments.application.query.getpaymentreceipt.GetPaymentReceiptHandler;
import org.onechurch.payments.application.query.getpaymentreceipt.GetPaymentReceiptQuery;
import org.onechurch.payments.presentation.api.PaymentReceiptsApi;
import org.onechurch.payments.presentation.api.PaymentsApi;
import org.springframework.http.CacheControl;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
@PreAuthorize("hasAuthority('payments.manage') and hasAuthority('payments.receipts.send')")
public class PaymentReceiptsController implements PaymentReceiptsApi {
    private final GetPaymentReceiptHandler get;
    private final ApprovePaymentReceiptHandler approve;
    private final RetryPaymentReceiptHandler retry;
    public PaymentReceiptsController(GetPaymentReceiptHandler get, ApprovePaymentReceiptHandler approve,
        RetryPaymentReceiptHandler retry) {
        this.get = get;
        this.approve = approve;
        this.retry = retry;
    }
    public ResponseEntity<GetPaymentReceiptHandler.Result> get(UUID orderId, UUID chargeId, UUID contactId) {
        return ResponseEntity.ok()
            .cacheControl(CacheControl.noStore())
            .body(get.handle(new GetPaymentReceiptQuery(orderId, chargeId, contactId)));
    }
    @Override
    public PaymentsApi.Created approve(UUID orderId, Approve b, OAuth2AuthenticatedPrincipal actor) {
        return new PaymentsApi.Created(approve.handle(new ApprovePaymentReceiptCommand(UUID.fromString(actor.getName()),
            b.requestId(), orderId, b.chargeId(), b.contactId(), b.reviewHash(), b.evidence())));
    }
    @Override
    public void retry(UUID orderId, UUID receiptId, Retry b, OAuth2AuthenticatedPrincipal actor) {
        retry.handle(new RetryPaymentReceiptCommand(UUID.fromString(actor.getName()), orderId, receiptId,
            b.expectedVersion(), b.reason()));
    }
}
