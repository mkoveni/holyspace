package org.onechurch.payments.infrastructure;
import org.onechurch.communications.application.contract.DeliveryEligibility;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.identityaccess.application.contract.ResponsibleAccountDirectory;
import org.onechurch.payments.application.PaymentReceiptPreview;
import org.onechurch.payments.application.contract.PaymentReader;
import org.onechurch.payments.application.contract.PaymentReceiptReader;
import org.onechurch.payments.domain.PaymentReceipt;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.springframework.stereotype.Component;

import java.time.Instant;
import java.util.UUID;
@Component
public final class PaymentDeliveryEligibility implements DeliveryEligibility {
    private final PaymentReceiptReader receipts;
    private final PaymentReader payments;
    private final PermissionChecker auth;
    private final ResponsibleAccountDirectory accounts;
    private final PersonDirectory people;
    private final PersonEmailDirectory emails;
    public PaymentDeliveryEligibility(PaymentReceiptReader receipts, PaymentReader payments, PermissionChecker auth,
        ResponsibleAccountDirectory accounts, PersonDirectory people, PersonEmailDirectory emails) {
        this.receipts = receipts;
        this.payments = payments;
        this.auth = auth;
        this.accounts = accounts;
        this.people = people;
        this.emails = emails;
    }
    public String sourceContext() {
        return "payments";
    }
    public void authorize(DeliverySnapshot d, UUID actor, String operation) {
        auth.requirePermission(actor, "payments.manage");
        auth.requirePermission(actor, "payments.receipts.send");
    }
    public boolean eligible(DeliverySnapshot d, Instant at) {
        if (!accounts.eligible(d.approvedBy(), "payments.manage")
            || !accounts.eligible(d.approvedBy(), "payments.receipts.send") || d.templateVersion() == null
            || !"payments.receipt".equals(d.templateKey())) {
            return false;
        }
        PaymentReceipt receipt = receipts.receipt(d.communicationId()).orElse(null);
        if (receipt == null || !receipt.deliveryId().equals(d.id()) || !receipt.reviewHash().equals(d.reviewHash())
            || !receipt.contactId().equals(d.contactId())
            || !people.get(receipt.donorId()).map(PersonDirectory.Entry::available).orElse(false)) {
            return false;
        }
        PaymentReader.Detail detail = payments.detail(receipt.orderId()).orElse(null);
        if (detail == null || !detail.order().mode().equals("live")
            || detail.refunds()
                .stream()
                .anyMatch(v -> v.chargeId() == null || v.chargeId().equals(receipt.chargeId()))) {
            return false;
        }
        PaymentReader.ChargeView charge =
            detail.charges().stream().filter(v -> v.charge().id().equals(receipt.chargeId())).findFirst().orElse(null);
        return charge != null && receipt.giftId().equals(charge.charge().giftId()) && charge.refundedMinor() == 0
            && emails.active(receipt.donorId())
                .stream()
                .anyMatch(v -> v.id().equals(d.contactId()) && v.value().equals(d.recipient()))
            && PaymentReceiptPreview
                .hash(detail, receipt.chargeId(), d.contactId(), d.recipient(), d.subject(), d.body(),
                    d.templateVersion())
                .equals(d.reviewHash());
    }
}
