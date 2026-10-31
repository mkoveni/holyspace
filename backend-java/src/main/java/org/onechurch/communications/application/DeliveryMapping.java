package org.onechurch.communications.application;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.communications.domain.EmailDelivery;
public final class DeliveryMapping {
    private DeliveryMapping() {
    }
    public static DeliverySnapshot snapshot(EmailDelivery d) {
        return new DeliverySnapshot(d.id(), d.communicationId(), d.verificationId(), d.contactId(), d.recipient(),
            d.subject(), d.body(), d.reviewHash(), d.approvedBy(), d.approvedAt(), d.scheduledAt(), d.status(),
            d.attempts(), d.messageId(), d.attemptId(), d.startedAt(), d.finishedAt(), d.errorCode(), d.captured(),
            d.version(), d.contentType(), d.templateKey(), d.templateVersion());
    }
    public static EmailDelivery aggregate(DeliverySnapshot d) {
        return new EmailDelivery(d.id(), d.communicationId(), d.verificationId(), d.contactId(), d.recipient(),
            d.subject(), d.body(), d.reviewHash(), d.approvedBy(), d.approvedAt(), d.scheduledAt(), d.status(),
            d.attempts(), d.messageId(), d.attemptId(), d.startedAt(), d.finishedAt(), d.errorCode(), d.captured(),
            d.version(), d.contentType(), d.templateKey(), d.templateVersion());
    }
}
