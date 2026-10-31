package org.onechurch.childrenministry.messaging.presentation;
import org.onechurch.childrenministry.messaging.application.command.cancelemaildelivery.CancelEmailDeliveryCommand;
import org.onechurch.childrenministry.messaging.application.command.cancelemaildelivery.CancelEmailDeliveryHandler;
import org.onechurch.childrenministry.messaging.application.command.queueguardianemail.QueueGuardianEmailCommand;
import org.onechurch.childrenministry.messaging.application.command.queueguardianemail.QueueGuardianEmailHandler;
import org.onechurch.childrenministry.messaging.application.command.recorddeliveryreceipt.RecordDeliveryReceiptCommand;
import org.onechurch.childrenministry.messaging.application.command.recorddeliveryreceipt.RecordDeliveryReceiptHandler;
import org.onechurch.childrenministry.messaging.application.command.retryemaildelivery.RetryEmailDeliveryCommand;
import org.onechurch.childrenministry.messaging.application.command.retryemaildelivery.RetryEmailDeliveryHandler;
import org.onechurch.childrenministry.messaging.application.command.revokeguardianemail.RevokeGuardianEmailCommand;
import org.onechurch.childrenministry.messaging.application.command.revokeguardianemail.RevokeGuardianEmailHandler;
import org.onechurch.childrenministry.messaging.application.command.verifyguardianemail.VerifyGuardianEmailCommand;
import org.onechurch.childrenministry.messaging.application.command.verifyguardianemail.VerifyGuardianEmailHandler;
import org.onechurch.childrenministry.messaging.application.contract.DeliveryReader;
import org.onechurch.childrenministry.messaging.application.query.getemaildelivery.GetEmailDeliveryHandler;
import org.onechurch.childrenministry.messaging.application.query.getemaildelivery.GetEmailDeliveryQuery;
import org.onechurch.childrenministry.messaging.application.query.getemailreview.GetEmailReviewHandler;
import org.onechurch.childrenministry.messaging.application.query.getemailreview.GetEmailReviewQuery;
import org.onechurch.childrenministry.messaging.application.query.listemaildeliveries.ListEmailDeliveriesHandler;
import org.onechurch.childrenministry.messaging.application.query.listemaildeliveries.ListEmailDeliveriesQuery;
import org.onechurch.childrenministry.messaging.presentation.api.EmailDeliveryApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
public class EmailDeliveryController implements EmailDeliveryApi {
    private final VerifyGuardianEmailHandler verifyGuardianEmail;
    private final RevokeGuardianEmailHandler revokeGuardianEmail;
    private final QueueGuardianEmailHandler queueGuardianEmail;
    private final CancelEmailDeliveryHandler cancelEmailDelivery;
    private final RetryEmailDeliveryHandler retryEmailDelivery;
    private final RecordDeliveryReceiptHandler recordDeliveryReceipt;
    private final GetEmailReviewHandler getEmailReview;
    private final ListEmailDeliveriesHandler listEmailDeliveries;
    private final GetEmailDeliveryHandler getEmailDelivery;
    public EmailDeliveryController(VerifyGuardianEmailHandler verifyGuardianEmail,
        RevokeGuardianEmailHandler revokeGuardianEmail, QueueGuardianEmailHandler queueGuardianEmail,
        CancelEmailDeliveryHandler cancelEmailDelivery, RetryEmailDeliveryHandler retryEmailDelivery,
        RecordDeliveryReceiptHandler recordDeliveryReceipt, GetEmailReviewHandler getEmailReview,
        ListEmailDeliveriesHandler listEmailDeliveries, GetEmailDeliveryHandler getEmailDelivery) {
        this.verifyGuardianEmail = verifyGuardianEmail;
        this.revokeGuardianEmail = revokeGuardianEmail;
        this.queueGuardianEmail = queueGuardianEmail;
        this.cancelEmailDelivery = cancelEmailDelivery;
        this.retryEmailDelivery = retryEmailDelivery;
        this.recordDeliveryReceipt = recordDeliveryReceipt;
        this.getEmailReview = getEmailReview;
        this.listEmailDeliveries = listEmailDeliveries;
        this.getEmailDelivery = getEmailDelivery;
    }
    @Override
    @PreAuthorize("hasAuthority('children.safeguarding')")
    public GetEmailReviewHandler.View review(UUID id) {
        return getEmailReview.handle(new GetEmailReviewQuery(id));
    }
    @Override
    @PreAuthorize("hasAuthority('children.safeguarding')")
    public Created verify(UUID id, Verify b, OAuth2AuthenticatedPrincipal p) {
        return new Created(verifyGuardianEmail.handle(new VerifyGuardianEmailCommand(UUID.fromString(p.getName()), id,
            b.contactId(), b.evidence(), b.validUntil())));
    }
    @Override
    @PreAuthorize("hasAuthority('children.safeguarding')")
    public void revoke(UUID id, Revoke b, OAuth2AuthenticatedPrincipal p) {
        revokeGuardianEmail.handle(new RevokeGuardianEmailCommand(UUID.fromString(p.getName()), id, b.reason()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.communications.send')")
    public Created queue(UUID id, Queue b, OAuth2AuthenticatedPrincipal p) {
        return new Created(queueGuardianEmail.handle(new QueueGuardianEmailCommand(UUID.fromString(p.getName()), id,
            b.expectedVersion(), b.verificationId(), b.reviewHash(), b.scheduledAt())));
    }
    @Override
    @PreAuthorize("hasAuthority('children.safeguarding')")
    public DeliveryReader.Page list(String status, int limit, int offset) {
        return listEmailDeliveries.handle(new ListEmailDeliveriesQuery(status, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('children.safeguarding')")
    public GetEmailDeliveryHandler.View get(UUID id, int limit, int offset) {
        return getEmailDelivery.handle(new GetEmailDeliveryQuery(id, limit, offset));
    }
    @Override
    @PreAuthorize("hasAuthority('children.communications.send')")
    public void cancel(UUID id, Change b, OAuth2AuthenticatedPrincipal p) {
        cancelEmailDelivery
            .handle(new CancelEmailDeliveryCommand(UUID.fromString(p.getName()), id, b.expectedVersion(), b.reason()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.communications.send')")
    public void retry(UUID id, Change b, OAuth2AuthenticatedPrincipal p) {
        retryEmailDelivery
            .handle(new RetryEmailDeliveryCommand(UUID.fromString(p.getName()), id, b.expectedVersion(), b.reason()));
    }
    @Override
    @PreAuthorize("hasAuthority('children.communications.receipts')")
    public void receipt(Receipt b, OAuth2AuthenticatedPrincipal p) {
        recordDeliveryReceipt.handle(new RecordDeliveryReceiptCommand(UUID.fromString(p.getName()), b.deliveryId(),
            b.messageId(), b.eventKey(), b.status(), b.reference()));
    }
}
