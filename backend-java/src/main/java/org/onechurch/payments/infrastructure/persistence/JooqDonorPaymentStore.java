package org.onechurch.payments.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_ORDERS;

import org.jooq.DSLContext;
import org.onechurch.communications.application.contract.DeliveryReader;
import org.onechurch.communications.application.contract.DeliverySnapshot;
import org.onechurch.payments.application.contract.DonorPaymentReader;
import org.onechurch.payments.application.contract.PaymentReader;
import org.onechurch.payments.application.contract.PaymentReceiptReader;
import org.onechurch.shared.infrastructure.jooq.tables.records.PaymentsOrdersRecord;
import org.springframework.beans.factory.annotation.Qualifier;
import org.springframework.stereotype.Repository;

import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqDonorPaymentStore implements DonorPaymentReader {
    private final DSLContext db;
    private final PaymentReader payments;
    private final PaymentReceiptReader receipts;
    private final DeliveryReader deliveries;
    public JooqDonorPaymentStore(DSLContext db, PaymentReader payments, PaymentReceiptReader receipts,
        @Qualifier("communicationDeliveryStore") DeliveryReader deliveries) {
        this.db = db;
        this.payments = payments;
        this.receipts = receipts;
        this.deliveries = deliveries;
    }
    private Order map(PaymentsOrdersRecord r) {
        return new Order(r.getId(), r.getFundId(), r.getAmountMinor(), r.getProvider(), r.getMode(), r.getRecurring(),
            r.getStatus(), r.getSubscriptionStatus(), r.getVersion(), r.getCreatedAt().toInstant());
    }
    public Page forDonor(UUID donor, int limit, int offset) {
        return new Page(db.selectFrom(PAYMENTS_ORDERS)
            .where(PAYMENTS_ORDERS.DONOR_ID.eq(donor))
            .orderBy(PAYMENTS_ORDERS.CREATED_AT.desc(), PAYMENTS_ORDERS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(this::map), db.fetchCount(PAYMENTS_ORDERS, PAYMENTS_ORDERS.DONOR_ID.eq(donor)));
    }
    public Optional<Detail> ownDetail(UUID donor, UUID id) {
        return db.selectFrom(PAYMENTS_ORDERS)
            .where(PAYMENTS_ORDERS.ID.eq(id).and(PAYMENTS_ORDERS.DONOR_ID.eq(donor)))
            .fetchOptional()
            .flatMap(r -> payments.detail(id).map(d -> new Detail(map(r), d.charges().stream().map(c -> {
                java.util.Optional<DeliverySnapshot> delivery =
                    receipts.forCharge(c.charge().id()).flatMap(receipt -> deliveries.delivery(receipt.deliveryId()));
                return new Charge(c.charge().id(), c.charge().amountMinor(), c.refundedMinor(), c.charge().receivedOn(),
                    c.givingStatus(), delivery.map(value -> value.status()).orElse("NOT_ISSUED"),
                    delivery.map(value -> value.captured()).orElse(false),
                    delivery.map(value -> new Confirmation(value.subject(), value.body())).orElse(null));
            }).toList(),
                d.refunds()
                    .stream()
                    .map(refund -> new Refund(refund.amountMinor(), refund.refundedOn(), refund.applied()))
                    .toList())));
    }
}
