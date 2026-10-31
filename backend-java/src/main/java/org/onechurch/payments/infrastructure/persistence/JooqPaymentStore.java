package org.onechurch.payments.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_CHARGES;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_HISTORY;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_NOTIFICATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_ORDERS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_REFUNDS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PAYMENTS_REVIEWS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.payments.application.contract.PaymentReader;
import org.onechurch.payments.domain.Charge;
import org.onechurch.payments.domain.PaymentOrder;
import org.onechurch.payments.domain.Refund;
import org.onechurch.payments.domain.SettlementReview;
import org.onechurch.payments.domain.repository.PaymentRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.infrastructure.jooq.tables.records.PaymentsChargesRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.PaymentsOrdersRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.PaymentsRefundsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.PaymentsReviewsRecord;
import org.springframework.stereotype.Repository;

import java.math.BigDecimal;
import java.time.Instant;
import java.time.ZoneOffset;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqPaymentStore implements PaymentRepository, PaymentReader {
    private final DSLContext db;
    private final PersonDirectory people;
    public JooqPaymentStore(DSLContext db, PersonDirectory people) {
        this.db = db;
        this.people = people;
    }
    private PaymentOrder order(PaymentsOrdersRecord r) {
        return new PaymentOrder(r.getId(), r.getDonorId(), r.getFundId(), r.getAmountMinor(), r.getProvider(),
            r.getMode(), r.getRecurring(), r.getStatus(), r.getCheckoutId(), r.getCheckoutUrl(), r.getCheckoutFields(),
            r.getSubscriptionStatus(), r.getEncryptedToken(), r.getTokenHash(), r.getReviewReason(), r.getVersion(),
            r.getRequestHash(), r.getCreatedBy(), r.getCreatedAt().toInstant(), r.getConfigurationId(),
            r.getSelfService());
    }
    private Charge charge(PaymentsChargesRecord r) {
        return new Charge(r.getId(), r.getOrderId(), r.getProvider(), r.getMode(), r.getReference(), r.getAmountMinor(),
            r.getReceivedOn(), r.getGiftId(), r.getCreatedAt().toInstant());
    }
    private Refund refund(PaymentsRefundsRecord r) {
        return new Refund(r.getId(), r.getOrderId(), r.getChargeId(), r.getProvider(), r.getMode(), r.getReference(),
            r.getAmountMinor(), r.getRefundedOn(), r.getApplied(), r.getReason(), r.getCreatedBy(),
            r.getCreatedAt().toInstant());
    }
    private SettlementReview review(PaymentsReviewsRecord r) {
        return new SettlementReview(r.getId(), r.getOrderId(), r.getChargeId(), r.getVersion(),
            r.getSettlementReference(), r.getFeeMinor(), r.getBankMinor(), r.getRefundedMinor(), r.getNote(),
            r.getCreatedBy(), r.getCreatedAt().toInstant(), r.getRequestHash());
    }
    @Override
    public Optional<PaymentOrder> find(UUID id) {
        return db.selectFrom(PAYMENTS_ORDERS).where(PAYMENTS_ORDERS.ID.eq(id)).fetchOptional().map(this::order);
    }
    @Override
    public Optional<PaymentOrder> lock(UUID id) {
        return db.selectFrom(PAYMENTS_ORDERS)
            .where(PAYMENTS_ORDERS.ID.eq(id))
            .forUpdate()
            .fetchOptional()
            .map(this::order);
    }
    @Override
    public boolean insert(PaymentOrder o) {
        return db.insertInto(PAYMENTS_ORDERS)
            .set(PAYMENTS_ORDERS.ID, o.id())
            .set(PAYMENTS_ORDERS.SELF_SERVICE, o.selfService())
            .set(PAYMENTS_ORDERS.CONFIGURATION_ID, o.configurationId())
            .set(PAYMENTS_ORDERS.DONOR_ID, o.donorId())
            .set(PAYMENTS_ORDERS.FUND_ID, o.fundId())
            .set(PAYMENTS_ORDERS.AMOUNT_MINOR, o.amountMinor())
            .set(PAYMENTS_ORDERS.PROVIDER, o.provider())
            .set(PAYMENTS_ORDERS.MODE, o.mode())
            .set(PAYMENTS_ORDERS.RECURRING, o.recurring())
            .set(PAYMENTS_ORDERS.STATUS, o.status())
            .set(PAYMENTS_ORDERS.SUBSCRIPTION_STATUS, o.subscriptionStatus())
            .set(PAYMENTS_ORDERS.REQUEST_HASH, o.requestHash())
            .set(PAYMENTS_ORDERS.CREATED_BY, o.createdBy())
            .set(PAYMENTS_ORDERS.CREATED_AT, o.createdAt().atOffset(ZoneOffset.UTC))
            .onConflictDoNothing()
            .execute() == 1;
    }
    @Override
    public void ready(UUID id, String checkout, String url, String fields) {
        db.update(PAYMENTS_ORDERS)
            .set(PAYMENTS_ORDERS.CHECKOUT_ID, checkout)
            .set(PAYMENTS_ORDERS.CHECKOUT_URL, url)
            .set(PAYMENTS_ORDERS.CHECKOUT_FIELDS, fields)
            .set(PAYMENTS_ORDERS.STATUS, "READY")
            .set(PAYMENTS_ORDERS.VERSION, PAYMENTS_ORDERS.VERSION.plus(1))
            .where(PAYMENTS_ORDERS.ID.eq(id).and(PAYMENTS_ORDERS.STATUS.in("PENDING", "READY")))
            .execute();
    }
    @Override
    public void status(UUID id, String status) {
        db.update(PAYMENTS_ORDERS)
            .set(PAYMENTS_ORDERS.STATUS, status)
            .set(PAYMENTS_ORDERS.VERSION, PAYMENTS_ORDERS.VERSION.plus(1))
            .where(PAYMENTS_ORDERS.ID.eq(id))
            .execute();
    }
    @Override
    public void review(UUID id, String reason) {
        db.update(PAYMENTS_ORDERS)
            .set(PAYMENTS_ORDERS.REVIEW_REASON, reason)
            .set(PAYMENTS_ORDERS.VERSION, PAYMENTS_ORDERS.VERSION.plus(1))
            .where(PAYMENTS_ORDERS.ID.eq(id))
            .execute();
    }
    @Override
    public void subscription(UUID id, String status, String encrypted, String hash) {
        db.update(PAYMENTS_ORDERS)
            .set(PAYMENTS_ORDERS.SUBSCRIPTION_STATUS, status)
            .set(PAYMENTS_ORDERS.ENCRYPTED_TOKEN, encrypted)
            .set(PAYMENTS_ORDERS.TOKEN_HASH, hash)
            .set(PAYMENTS_ORDERS.VERSION, PAYMENTS_ORDERS.VERSION.plus(1))
            .where(PAYMENTS_ORDERS.ID.eq(id))
            .execute();
    }
    @Override
    public Optional<UUID> lookup(String provider, String mode, String checkout, String tokenHash) {
        if (checkout == null && tokenHash == null) {
            return Optional.empty();
        }
        return db.select(PAYMENTS_ORDERS.ID)
            .from(PAYMENTS_ORDERS)
            .where(PAYMENTS_ORDERS.PROVIDER.eq(provider)
                .and(PAYMENTS_ORDERS.MODE.eq(mode))
                .and(checkout == null ? PAYMENTS_ORDERS.TOKEN_HASH.eq(tokenHash)
                    : PAYMENTS_ORDERS.CHECKOUT_ID.eq(checkout)))
            .fetchOptional(PAYMENTS_ORDERS.ID);
    }
    @Override
    public Optional<String> notification(String provider, String mode, String event) {
        return db.select(PAYMENTS_NOTIFICATIONS.FINGERPRINT)
            .from(PAYMENTS_NOTIFICATIONS)
            .where(PAYMENTS_NOTIFICATIONS.PROVIDER.eq(provider)
                .and(PAYMENTS_NOTIFICATIONS.MODE.eq(mode))
                .and(PAYMENTS_NOTIFICATIONS.EVENT_ID.eq(event)))
            .fetchOptional(PAYMENTS_NOTIFICATIONS.FINGERPRINT);
    }
    @Override
    public boolean remember(String provider, String mode, String event, UUID order, String hash, Instant now) {
        return db.insertInto(PAYMENTS_NOTIFICATIONS)
            .set(PAYMENTS_NOTIFICATIONS.PROVIDER, provider)
            .set(PAYMENTS_NOTIFICATIONS.MODE, mode)
            .set(PAYMENTS_NOTIFICATIONS.EVENT_ID, event)
            .set(PAYMENTS_NOTIFICATIONS.ORDER_ID, order)
            .set(PAYMENTS_NOTIFICATIONS.FINGERPRINT, hash)
            .set(PAYMENTS_NOTIFICATIONS.RECEIVED_AT, now.atOffset(ZoneOffset.UTC))
            .onConflictDoNothing()
            .execute() == 1;
    }
    @Override
    public Optional<Charge> charge(String provider, String mode, String reference) {
        return db.selectFrom(PAYMENTS_CHARGES)
            .where(PAYMENTS_CHARGES.PROVIDER.eq(provider)
                .and(PAYMENTS_CHARGES.MODE.eq(mode))
                .and(PAYMENTS_CHARGES.REFERENCE.eq(reference)))
            .fetchOptional()
            .map(this::charge);
    }
    @Override
    public boolean insert(Charge c) {
        return db.insertInto(PAYMENTS_CHARGES)
            .set(PAYMENTS_CHARGES.ID, c.id())
            .set(PAYMENTS_CHARGES.ORDER_ID, c.orderId())
            .set(PAYMENTS_CHARGES.PROVIDER, c.provider())
            .set(PAYMENTS_CHARGES.MODE, c.mode())
            .set(PAYMENTS_CHARGES.REFERENCE, c.reference())
            .set(PAYMENTS_CHARGES.AMOUNT_MINOR, c.amountMinor())
            .set(PAYMENTS_CHARGES.RECEIVED_ON, c.receivedOn())
            .set(PAYMENTS_CHARGES.CREATED_AT, c.createdAt().atOffset(ZoneOffset.UTC))
            .onConflictDoNothing()
            .execute() == 1;
    }
    @Override
    public List<Charge> charges(UUID order) {
        return db.selectFrom(PAYMENTS_CHARGES)
            .where(PAYMENTS_CHARGES.ORDER_ID.eq(order))
            .orderBy(PAYMENTS_CHARGES.CREATED_AT, PAYMENTS_CHARGES.ID)
            .limit(1001)
            .fetch(this::charge);
    }
    @Override
    public List<Charge> pendingCharges(UUID order) {
        return db.selectFrom(PAYMENTS_CHARGES)
            .where(PAYMENTS_CHARGES.ORDER_ID.eq(order).and(PAYMENTS_CHARGES.GIFT_ID.isNull()))
            .orderBy(PAYMENTS_CHARGES.CREATED_AT, PAYMENTS_CHARGES.ID)
            .limit(100)
            .fetch(this::charge);
    }
    @Override
    public List<Charge> matchingCharges(UUID order, UUID charge) {
        return db.selectFrom(PAYMENTS_CHARGES)
            .where(PAYMENTS_CHARGES.ORDER_ID.eq(order)
                .and(charge == null ? DSL.trueCondition() : PAYMENTS_CHARGES.ID.eq(charge)))
            .orderBy(PAYMENTS_CHARGES.ID)
            .limit(2)
            .fetch(this::charge);
    }
    @Override
    public void posted(UUID charge, UUID gift) {
        db.update(PAYMENTS_CHARGES)
            .set(PAYMENTS_CHARGES.GIFT_ID, gift)
            .where(PAYMENTS_CHARGES.ID.eq(charge).and(PAYMENTS_CHARGES.GIFT_ID.isNull()))
            .execute();
    }
    @Override
    public Optional<Refund> refund(String provider, String mode, String reference) {
        return db.selectFrom(PAYMENTS_REFUNDS)
            .where(PAYMENTS_REFUNDS.PROVIDER.eq(provider)
                .and(PAYMENTS_REFUNDS.MODE.eq(mode))
                .and(PAYMENTS_REFUNDS.REFERENCE.eq(reference)))
            .fetchOptional()
            .map(this::refund);
    }
    @Override
    public boolean insert(Refund r) {
        return db.insertInto(PAYMENTS_REFUNDS)
            .set(PAYMENTS_REFUNDS.ID, r.id())
            .set(PAYMENTS_REFUNDS.ORDER_ID, r.orderId())
            .set(PAYMENTS_REFUNDS.CHARGE_ID, r.chargeId())
            .set(PAYMENTS_REFUNDS.PROVIDER, r.provider())
            .set(PAYMENTS_REFUNDS.MODE, r.mode())
            .set(PAYMENTS_REFUNDS.REFERENCE, r.reference())
            .set(PAYMENTS_REFUNDS.AMOUNT_MINOR, r.amountMinor())
            .set(PAYMENTS_REFUNDS.REFUNDED_ON, r.refundedOn())
            .set(PAYMENTS_REFUNDS.REASON, r.reason())
            .set(PAYMENTS_REFUNDS.CREATED_BY, r.createdBy())
            .set(PAYMENTS_REFUNDS.CREATED_AT, r.createdAt().atOffset(ZoneOffset.UTC))
            .onConflictDoNothing()
            .execute() == 1;
    }
    @Override
    public List<Refund> refunds(UUID order) {
        return db.selectFrom(PAYMENTS_REFUNDS)
            .where(PAYMENTS_REFUNDS.ORDER_ID.eq(order))
            .orderBy(PAYMENTS_REFUNDS.CREATED_AT, PAYMENTS_REFUNDS.ID)
            .limit(1001)
            .fetch(this::refund);
    }
    @Override
    public List<Refund> pendingRefunds(UUID order) {
        return db.selectFrom(PAYMENTS_REFUNDS)
            .where(PAYMENTS_REFUNDS.ORDER_ID.eq(order).and(PAYMENTS_REFUNDS.APPLIED.isFalse()))
            .orderBy(PAYMENTS_REFUNDS.CREATED_AT, PAYMENTS_REFUNDS.ID)
            .limit(100)
            .fetch(this::refund);
    }
    @Override
    public void applied(UUID refund, UUID charge) {
        db.update(PAYMENTS_REFUNDS)
            .set(PAYMENTS_REFUNDS.APPLIED, true)
            .set(PAYMENTS_REFUNDS.CHARGE_ID, charge)
            .where(PAYMENTS_REFUNDS.ID.eq(refund).and(PAYMENTS_REFUNDS.APPLIED.isFalse()))
            .execute();
    }
    @Override
    public void matchRefund(UUID refund, UUID charge) {
        db.update(PAYMENTS_REFUNDS)
            .set(PAYMENTS_REFUNDS.CHARGE_ID, charge)
            .where(PAYMENTS_REFUNDS.ID.eq(refund)
                .and(PAYMENTS_REFUNDS.CHARGE_ID.isNull())
                .and(PAYMENTS_REFUNDS.APPLIED.isFalse()))
            .execute();
    }
    @Override
    public long refunded(UUID charge) {
        return db.select(DSL.coalesce(DSL.sum(PAYMENTS_REFUNDS.AMOUNT_MINOR), BigDecimal.ZERO))
            .from(PAYMENTS_REFUNDS)
            .where(PAYMENTS_REFUNDS.CHARGE_ID.eq(charge).and(PAYMENTS_REFUNDS.APPLIED.isTrue()))
            .fetchOne(0, BigDecimal.class)
            .longValueExact();
    }
    @Override
    public Optional<SettlementReview> reviewRequest(UUID request) {
        return db.selectFrom(PAYMENTS_REVIEWS).where(PAYMENTS_REVIEWS.ID.eq(request)).fetchOptional().map(this::review);
    }
    @Override
    public Optional<SettlementReview> latestReview(UUID charge) {
        return db.selectFrom(PAYMENTS_REVIEWS)
            .where(PAYMENTS_REVIEWS.CHARGE_ID.eq(charge))
            .orderBy(PAYMENTS_REVIEWS.VERSION.desc())
            .limit(1)
            .fetchOptional()
            .map(this::review);
    }
    @Override
    public void insert(SettlementReview r) {
        db.insertInto(PAYMENTS_REVIEWS)
            .set(PAYMENTS_REVIEWS.ID, r.id())
            .set(PAYMENTS_REVIEWS.ORDER_ID, r.orderId())
            .set(PAYMENTS_REVIEWS.CHARGE_ID, r.chargeId())
            .set(PAYMENTS_REVIEWS.VERSION, r.version())
            .set(PAYMENTS_REVIEWS.SETTLEMENT_REFERENCE, r.settlementReference())
            .set(PAYMENTS_REVIEWS.FEE_MINOR, r.feeMinor())
            .set(PAYMENTS_REVIEWS.BANK_MINOR, r.bankMinor())
            .set(PAYMENTS_REVIEWS.REFUNDED_MINOR, r.refundedMinor())
            .set(PAYMENTS_REVIEWS.NOTE, r.note())
            .set(PAYMENTS_REVIEWS.CREATED_BY, r.createdBy())
            .set(PAYMENTS_REVIEWS.CREATED_AT, r.createdAt().atOffset(ZoneOffset.UTC))
            .set(PAYMENTS_REVIEWS.REQUEST_HASH, r.requestHash())
            .execute();
    }
    @Override
    public void audit(UUID order, String action, String note, UUID actor, Instant now) {
        db.insertInto(PAYMENTS_HISTORY)
            .set(PAYMENTS_HISTORY.ID, UUID.randomUUID())
            .set(PAYMENTS_HISTORY.ORDER_ID, order)
            .set(PAYMENTS_HISTORY.ACTION, action)
            .set(PAYMENTS_HISTORY.NOTE, note)
            .set(PAYMENTS_HISTORY.ACTOR_ID, actor)
            .set(PAYMENTS_HISTORY.OCCURRED_AT, now.atOffset(ZoneOffset.UTC))
            .execute();
    }
    private Summary summary(PaymentsOrdersRecord r) {
        return new Summary(r.getId(), r.getDonorId(),
            people.get(r.getDonorId()).map(PersonDirectory.Entry::name).orElse("Person unavailable"), r.getFundId(),
            r.getAmountMinor(), r.getProvider(), r.getMode(), r.getRecurring(), r.getStatus(),
            r.getSubscriptionStatus(), r.getReviewReason(), r.getVersion(), r.getCreatedAt().toInstant(),
            r.getMode().equals("test") ? "SIMULATION"
                : !r.getStatus().equals("PAID") ? "AWAITING_PAYMENT"
                    : db.fetchExists(db.selectOne()
                        .from(PAYMENTS_CHARGES)
                        .where(PAYMENTS_CHARGES.ORDER_ID.eq(r.getId()).and(PAYMENTS_CHARGES.GIFT_ID.isNull())))
                            ? "POSTING_PENDING" : "POSTED");
    }
    @Override
    public Page list(int limit, int offset, boolean review) {
        Condition where = review ? PAYMENTS_ORDERS.REVIEW_REASON.isNotNull() : DSL.trueCondition();
        return new Page(db.selectFrom(PAYMENTS_ORDERS)
            .where(where)
            .orderBy(PAYMENTS_ORDERS.CREATED_AT.desc(), PAYMENTS_ORDERS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(this::summary), db.fetchCount(PAYMENTS_ORDERS, where));
    }
    @Override
    public Optional<Detail> detail(UUID id) {
        return db.selectFrom(PAYMENTS_ORDERS).where(PAYMENTS_ORDERS.ID.eq(id)).fetchOptional().map(r -> {
            List<Charge> charges = charges(id);
            if (charges.size() > 1000 || refunds(id).size() > 1000) {
                throw org.onechurch.payments.domain.PaymentException.conflict(
                    "This payment has too much history for this view. Contact support for an archival report.");
            }
            return new Detail(summary(r), charges.stream().map(c -> {
                long refunded = refunded(c.id());
                SettlementReview review = latestReview(c.id()).orElse(null);
                return new ChargeView(c, refunded, review, review == null || review.refundedMinor() != refunded
                    || c.amountMinor() - refunded - review.feeMinor() != review.bankMinor());
            }).toList(), refunds(id), db.selectFrom(PAYMENTS_HISTORY)
                .where(PAYMENTS_HISTORY.ORDER_ID.eq(id))
                .orderBy(PAYMENTS_HISTORY.OCCURRED_AT.desc(), PAYMENTS_HISTORY.ID)
                .limit(100)
                .fetch(h -> new History(h.getAction(), h.getNote(), h.getActorId(), h.getOccurredAt().toInstant())));
        });
    }
}
