package org.onechurch.payments.application;
import org.onechurch.communications.application.contract.EmailTemplates;
import org.onechurch.payments.application.contract.PaymentReader;
import org.onechurch.payments.domain.PaymentException;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.people.application.contract.PersonEmailDirectory;

import java.math.BigDecimal;
import java.util.List;
import java.util.Map;
import java.util.UUID;
public final class PaymentReceiptPreview {
    public record Preview(String hash, String recipient, String subject, String html, long templateVersion) {
        @Override
        public String toString() {
            return "PaymentReceiptPreview[redacted]";
        }
    }
    private final PaymentReader payments;
    private final PersonDirectory people;
    private final PersonEmailDirectory emails;
    private final EmailTemplates templates;
    public PaymentReceiptPreview(PaymentReader payments, PersonDirectory people, PersonEmailDirectory emails,
        EmailTemplates templates) {
        this.payments = payments;
        this.people = people;
        this.emails = emails;
        this.templates = templates;
    }
    public PaymentReader.Detail detail(UUID orderId) {
        return payments.detail(orderId).orElseThrow(PaymentException::missing);
    }
    public PaymentReader.Detail eligible(UUID orderId, UUID chargeId) {
        PaymentReader.Detail detail = payments.detail(orderId).orElseThrow(PaymentException::missing);
        PaymentReader.ChargeView charge = charge(detail, chargeId);
        if (!detail.order().mode().equals("live") || charge.charge().giftId() == null || charge.refundedMinor() != 0
            || detail.refunds().stream().anyMatch(v -> v.chargeId() == null || v.chargeId().equals(chargeId))) {
            throw PaymentException
                .conflict("Confirmations require a verified live contribution posted to Giving with no refunds.");
        }
        return detail;
    }
    public static PaymentReader.ChargeView charge(PaymentReader.Detail detail, UUID id) {
        return detail.charges()
            .stream()
            .filter(c -> c.charge().id().equals(id))
            .findFirst()
            .orElseThrow(PaymentException::missing);
    }
    public List<PersonEmailDirectory.Email> contacts(PaymentReader.Detail detail) {
        if (!people.get(detail.order().donorId()).map(PersonDirectory.Entry::available).orElse(false)) {
            throw PaymentException.conflict("The donor is unavailable for a receipt.");
        }
        return emails.active(detail.order().donorId());
    }
    public Preview preview(PaymentReader.Detail detail, UUID chargeId, UUID contact) {
        PaymentReader.ChargeView charge = charge(detail, chargeId);
        String recipient = contacts(detail).stream()
            .filter(e -> e.id().equals(contact))
            .map(PersonEmailDirectory.Email::value)
            .findFirst()
            .orElseThrow(() -> PaymentException.conflict("Choose a current email address belonging to this donor."));
        EmailTemplates.Rendered rendered = templates.render("payments.receipt",
            Map.of("name", detail.order().donorName(), "amount",
                "ZAR " + BigDecimal.valueOf(charge.charge().amountMinor(), 2).toPlainString(), "date",
                charge.charge().receivedOn().toString(), "provider", detail.order().provider(), "reference",
                charge.charge().reference()));
        return new Preview(
            hash(detail, chargeId, contact, recipient, rendered.subject(), rendered.html(), rendered.version()),
            recipient, rendered.subject(), rendered.html(), rendered.version());
    }
    public static String hash(PaymentReader.Detail detail, UUID chargeId, UUID contact, String recipient,
        String subject, String html, long version) {
        PaymentReader.ChargeView charge = charge(detail, chargeId);
        return PaymentHash.of(detail.order().id(), chargeId, charge.charge().giftId(), charge.charge().amountMinor(),
            charge.refundedMinor(), contact, recipient, subject, html, version);
    }
}
