package org.onechurch.giving.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_PAYMENT_REFUNDS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_PAYMENT_SOURCES;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_PLEDGE_ASSIGNMENTS;

import org.jooq.DSLContext;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.PledgeAssignment;
import org.onechurch.giving.domain.repository.OnlineGivingRepository;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneOffset;
import java.util.List;
import java.util.UUID;
@Repository
public class JooqOnlineGivingStore implements OnlineGivingRepository {
    private final DSLContext db;
    public JooqOnlineGivingStore(DSLContext db) {
        this.db = db;
    }
    @Override
    public void source(UUID gift, UUID charge, String reference) {
        db.insertInto(GIVING_PAYMENT_SOURCES)
            .set(GIVING_PAYMENT_SOURCES.GIFT_ID, gift)
            .set(GIVING_PAYMENT_SOURCES.CHARGE_ID, charge)
            .set(GIVING_PAYMENT_SOURCES.REFERENCE, reference)
            .execute();
    }
    @Override
    public boolean sourceMatches(UUID gift, String reference) {
        return db.fetchExists(GIVING_PAYMENT_SOURCES,
            GIVING_PAYMENT_SOURCES.GIFT_ID.eq(gift).and(GIVING_PAYMENT_SOURCES.REFERENCE.eq(reference)));
    }
    @Override
    public boolean refundExists(UUID id, UUID gift, long amount, String reference, LocalDate date) {
        return db.selectFrom(GIVING_PAYMENT_REFUNDS).where(GIVING_PAYMENT_REFUNDS.ID.eq(id)).fetchOptional().map(r -> {
            if (!r.getGiftId().equals(gift) || r.getAmountMinor() != amount || !r.getReference().equals(reference)
                || !r.getRefundedOn().equals(date))
                throw GivingException.conflict("Refund identity conflicts with existing evidence.");
            return true;
        }).orElse(false);
    }
    @Override
    public void refund(UUID id, UUID gift, UUID fund, long amount, String reference, LocalDate date, UUID actor,
        Instant now) {
        db.insertInto(GIVING_PAYMENT_REFUNDS)
            .set(GIVING_PAYMENT_REFUNDS.ID, id)
            .set(GIVING_PAYMENT_REFUNDS.GIFT_ID, gift)
            .set(GIVING_PAYMENT_REFUNDS.FUND_ID, fund)
            .set(GIVING_PAYMENT_REFUNDS.AMOUNT_MINOR, amount)
            .set(GIVING_PAYMENT_REFUNDS.REFERENCE, reference)
            .set(GIVING_PAYMENT_REFUNDS.REFUNDED_ON, date)
            .set(GIVING_PAYMENT_REFUNDS.CREATED_BY, actor)
            .set(GIVING_PAYMENT_REFUNDS.CREATED_AT, now.atOffset(ZoneOffset.UTC))
            .execute();
    }
    @Override
    public List<PledgeAssignment> activeAssignments(UUID gift) {
        return db.selectFrom(GIVING_PLEDGE_ASSIGNMENTS)
            .where(GIVING_PLEDGE_ASSIGNMENTS.GIFT_ID.eq(gift).and(GIVING_PLEDGE_ASSIGNMENTS.STATUS.eq("ACTIVE")))
            .orderBy(GIVING_PLEDGE_ASSIGNMENTS.PLEDGE_ID, GIVING_PLEDGE_ASSIGNMENTS.ID)
            .fetch(r -> new PledgeAssignment(r.getId(), r.getPledgeId(), r.getGiftId(), r.getFundId(), r.getDonorId(),
                r.getCurrency(), r.getAmountMinor(), r.getStatus(), r.getVersion(), r.getCancellationReason(),
                r.getCancelledBy(), r.getCancelledAt() == null ? null : r.getCancelledAt().toInstant(),
                r.getCreatedBy(), r.getCreatedAt().toInstant()));
    }
}
