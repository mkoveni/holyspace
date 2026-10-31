package org.onechurch.giving.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_ACKNOWLEDGEMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_ACKNOWLEDGEMENT_LINES;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_GIFTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_PAYMENT_REFUNDS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_REVERSALS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.onechurch.giving.application.contract.AcknowledgementReader;
import org.onechurch.giving.domain.Acknowledgement;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.repository.AcknowledgementRepository;
import org.springframework.stereotype.Repository;

import java.time.ZoneOffset;
import java.util.List;
import java.util.Locale;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqAcknowledgementStore implements AcknowledgementReader, AcknowledgementRepository {
    private final DSLContext db;
    public JooqAcknowledgementStore(DSLContext db) {
        this.db = db;
    }
    @Override
    public Optional<UUID> request(UUID id, String hash) {
        return db.select(GIVING_ACKNOWLEDGEMENTS.REQUEST_HASH)
            .from(GIVING_ACKNOWLEDGEMENTS)
            .where(GIVING_ACKNOWLEDGEMENTS.ID.eq(id))
            .fetchOptional(GIVING_ACKNOWLEDGEMENTS.REQUEST_HASH)
            .map(saved -> {
                if (!saved.equals(hash)) {
                    throw GivingException.conflict("This request identifier was already used.");
                }
                return id;
            });
    }
    @Override
    public Optional<UUID> forGift(UUID giftId) {
        return db.select(GIVING_ACKNOWLEDGEMENTS.ID)
            .from(GIVING_ACKNOWLEDGEMENTS)
            .where(GIVING_ACKNOWLEDGEMENTS.GIFT_ID.eq(giftId))
            .fetchOptional(GIVING_ACKNOWLEDGEMENTS.ID);
    }
    @Override
    public void insert(Acknowledgement a, String hash) {
        db.insertInto(GIVING_ACKNOWLEDGEMENTS)
            .set(GIVING_ACKNOWLEDGEMENTS.ID, a.id())
            .set(GIVING_ACKNOWLEDGEMENTS.GIFT_ID, a.giftId())
            .set(GIVING_ACKNOWLEDGEMENTS.DONOR_NAME, a.donorName())
            .set(GIVING_ACKNOWLEDGEMENTS.ISSUED_ON, a.issuedOn())
            .set(GIVING_ACKNOWLEDGEMENTS.CREATED_BY, a.createdBy())
            .set(GIVING_ACKNOWLEDGEMENTS.CREATED_AT, a.createdAt().atOffset(ZoneOffset.UTC))
            .set(GIVING_ACKNOWLEDGEMENTS.REQUEST_HASH, hash)
            .execute();
        for (Acknowledgement.Line line : a.lines()) {
            db.insertInto(GIVING_ACKNOWLEDGEMENT_LINES)
                .set(GIVING_ACKNOWLEDGEMENT_LINES.ACKNOWLEDGEMENT_ID, a.id())
                .set(GIVING_ACKNOWLEDGEMENT_LINES.FUND_ID, line.fundId())
                .set(GIVING_ACKNOWLEDGEMENT_LINES.FUND_NAME, line.fundName())
                .set(GIVING_ACKNOWLEDGEMENT_LINES.AMOUNT_MINOR, line.amountMinor())
                .execute();
        }
    }
    private Optional<View> view(Condition where) {
        return db.select(GIVING_ACKNOWLEDGEMENTS.fields())
            .select(GIVING_GIFTS.RECEIVED_ON, GIVING_GIFTS.AMOUNT_MINOR, GIVING_GIFTS.CURRENCY, GIVING_GIFTS.METHOD,
                GIVING_GIFTS.EXTERNAL_REFERENCE, GIVING_REVERSALS.REVERSED_ON, GIVING_REVERSALS.REASON)
            .from(GIVING_ACKNOWLEDGEMENTS)
            .join(GIVING_GIFTS)
            .on(GIVING_GIFTS.ID.eq(GIVING_ACKNOWLEDGEMENTS.GIFT_ID))
            .leftJoin(GIVING_REVERSALS)
            .on(GIVING_REVERSALS.GIFT_ID.eq(GIVING_GIFTS.ID))
            .where(where)
            .fetchOptional()
            .map(r -> {
                UUID id = r.get(GIVING_ACKNOWLEDGEMENTS.ID);
                List<Acknowledgement.Line> lines = db.selectFrom(GIVING_ACKNOWLEDGEMENT_LINES)
                    .where(GIVING_ACKNOWLEDGEMENT_LINES.ACKNOWLEDGEMENT_ID.eq(id))
                    .orderBy(GIVING_ACKNOWLEDGEMENT_LINES.FUND_NAME, GIVING_ACKNOWLEDGEMENT_LINES.FUND_ID)
                    .fetch(l -> new Acknowledgement.Line(l.getFundId(), l.getFundName(), l.getAmountMinor()));
                java.time.LocalDate refundDate = db.select(org.jooq.impl.DSL.min(GIVING_PAYMENT_REFUNDS.REFUNDED_ON))
                    .from(GIVING_PAYMENT_REFUNDS)
                    .where(GIVING_PAYMENT_REFUNDS.GIFT_ID.eq(r.get(GIVING_ACKNOWLEDGEMENTS.GIFT_ID)))
                    .fetchOne(0, java.time.LocalDate.class);
                return new View(id, r.get(GIVING_ACKNOWLEDGEMENTS.GIFT_ID),
                    String.format(Locale.ROOT, "G-%08d", r.get(GIVING_ACKNOWLEDGEMENTS.NUMBER)),
                    r.get(GIVING_ACKNOWLEDGEMENTS.DONOR_NAME), r.get(GIVING_ACKNOWLEDGEMENTS.ISSUED_ON),
                    r.get(GIVING_GIFTS.RECEIVED_ON), r.get(GIVING_GIFTS.AMOUNT_MINOR), r.get(GIVING_GIFTS.CURRENCY),
                    r.get(GIVING_GIFTS.METHOD), r.get(GIVING_GIFTS.EXTERNAL_REFERENCE),
                    r.get(GIVING_REVERSALS.REVERSED_ON) != null || refundDate != null,
                    refundDate == null ? r.get(GIVING_REVERSALS.REVERSED_ON) : refundDate,
                    refundDate == null ? r.get(GIVING_REVERSALS.REASON) : "Provider-completed refund", lines);
            });
    }
    @Override
    public Optional<View> get(UUID id) {
        return view(GIVING_ACKNOWLEDGEMENTS.ID.eq(id));
    }
    @Override
    public Optional<View> findForGift(UUID giftId) {
        return view(GIVING_ACKNOWLEDGEMENTS.GIFT_ID.eq(giftId));
    }
}
