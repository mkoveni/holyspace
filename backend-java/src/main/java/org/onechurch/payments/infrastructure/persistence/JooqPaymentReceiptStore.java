package org.onechurch.payments.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_RECEIPTS;

import org.jooq.DSLContext;
import org.onechurch.payments.application.contract.PaymentReceiptReader;
import org.onechurch.payments.domain.PaymentReceipt;
import org.onechurch.payments.domain.repository.PaymentReceiptRepository;
import org.onechurch.shared.infrastructure.jooq.tables.records.PaymentsReceiptsRecord;
import org.springframework.stereotype.Repository;

import java.time.ZoneOffset;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqPaymentReceiptStore implements PaymentReceiptReader, PaymentReceiptRepository {
    private final DSLContext db;
    public JooqPaymentReceiptStore(DSLContext db) {
        this.db = db;
    }
    private PaymentReceipt map(PaymentsReceiptsRecord r) {
        return new PaymentReceipt(r.getId(), r.getOrderId(), r.getChargeId(), r.getDonorId(), r.getContactId(),
            r.getGiftId(), r.getDeliveryId(), r.getApprovedBy(), r.getReviewHash(), r.getRequestHash(), r.getEvidence(),
            r.getCreatedAt().toInstant());
    }
    public Optional<PaymentReceipt> receipt(UUID id) {
        return db.selectFrom(PAYMENTS_RECEIPTS).where(PAYMENTS_RECEIPTS.ID.eq(id)).fetchOptional(this::map);
    }
    public Optional<PaymentReceipt> forCharge(UUID id) {
        return db.selectFrom(PAYMENTS_RECEIPTS).where(PAYMENTS_RECEIPTS.CHARGE_ID.eq(id)).fetchOptional(this::map);
    }
    public void insert(PaymentReceipt r) {
        db.insertInto(PAYMENTS_RECEIPTS)
            .set(PAYMENTS_RECEIPTS.ID, r.id())
            .set(PAYMENTS_RECEIPTS.ORDER_ID, r.orderId())
            .set(PAYMENTS_RECEIPTS.CHARGE_ID, r.chargeId())
            .set(PAYMENTS_RECEIPTS.DONOR_ID, r.donorId())
            .set(PAYMENTS_RECEIPTS.CONTACT_ID, r.contactId())
            .set(PAYMENTS_RECEIPTS.GIFT_ID, r.giftId())
            .set(PAYMENTS_RECEIPTS.DELIVERY_ID, r.deliveryId())
            .set(PAYMENTS_RECEIPTS.APPROVED_BY, r.approvedBy())
            .set(PAYMENTS_RECEIPTS.REVIEW_HASH, r.reviewHash())
            .set(PAYMENTS_RECEIPTS.REQUEST_HASH, r.requestHash())
            .set(PAYMENTS_RECEIPTS.EVIDENCE, r.evidence())
            .set(PAYMENTS_RECEIPTS.CREATED_AT, r.createdAt().atOffset(ZoneOffset.UTC))
            .execute();
    }
}
