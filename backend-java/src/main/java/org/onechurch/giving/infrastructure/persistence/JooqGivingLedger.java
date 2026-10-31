package org.onechurch.giving.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_ALLOCATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_FUNDS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_GIFTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_PAYMENT_REFUNDS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_REVERSALS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.giving.application.contract.GivingLedgerReader;
import org.onechurch.giving.application.contract.GivingReader;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.people.application.contract.PersonDirectory;
import org.springframework.stereotype.Repository;

import java.util.List;
import java.util.Map;
import java.util.Objects;
import java.util.UUID;
import java.util.stream.Collectors;
@Repository
public class JooqGivingLedger implements GivingLedgerReader {
    private final DSLContext db;
    private final PersonDirectory people;
    public JooqGivingLedger(DSLContext db, PersonDirectory people) {
        this.db = db;
        this.people = people;
    }
    @Override
    public List<Line> lines(GivingReader.Filter f, int maximum) {
        Condition attributes = (f.donorId() == null ? DSL.trueCondition() : GIVING_GIFTS.DONOR_ID.eq(f.donorId()))
            .and(f.fundId() == null ? DSL.trueCondition() : GIVING_ALLOCATIONS.FUND_ID.eq(f.fundId()))
            .and(f.currency() == null ? DSL.trueCondition() : GIVING_GIFTS.CURRENCY.eq(f.currency()));
        org.jooq.Result<org.jooq.Record11<UUID, String, java.time.LocalDate, UUID, UUID, String, String, Long, String, String, String>> rows =
            db.select(GIVING_GIFTS.ID, DSL.inline("GIFT"), GIVING_GIFTS.RECEIVED_ON, GIVING_GIFTS.DONOR_ID,
                GIVING_FUNDS.ID, GIVING_FUNDS.NAME, GIVING_GIFTS.CURRENCY, GIVING_ALLOCATIONS.AMOUNT_MINOR,
                GIVING_GIFTS.METHOD, GIVING_GIFTS.EXTERNAL_REFERENCE, DSL.inline(""))
                .from(GIVING_GIFTS)
                .join(GIVING_ALLOCATIONS)
                .on(GIVING_ALLOCATIONS.GIFT_ID.eq(GIVING_GIFTS.ID))
                .join(GIVING_FUNDS)
                .on(GIVING_FUNDS.ID.eq(GIVING_ALLOCATIONS.FUND_ID))
                .where(attributes.and(GIVING_GIFTS.RECEIVED_ON.between(f.from(), f.to())))
                .unionAll(db
                    .select(GIVING_GIFTS.ID, DSL.inline("REVERSAL"), GIVING_REVERSALS.REVERSED_ON,
                        GIVING_GIFTS.DONOR_ID, GIVING_FUNDS.ID, GIVING_FUNDS.NAME, GIVING_GIFTS.CURRENCY,
                        GIVING_ALLOCATIONS.AMOUNT_MINOR.neg(), GIVING_GIFTS.METHOD, GIVING_GIFTS.EXTERNAL_REFERENCE,
                        GIVING_REVERSALS.REASON)
                    .from(GIVING_GIFTS)
                    .join(GIVING_ALLOCATIONS)
                    .on(GIVING_ALLOCATIONS.GIFT_ID.eq(GIVING_GIFTS.ID))
                    .join(GIVING_FUNDS)
                    .on(GIVING_FUNDS.ID.eq(GIVING_ALLOCATIONS.FUND_ID))
                    .join(GIVING_REVERSALS)
                    .on(GIVING_REVERSALS.GIFT_ID.eq(GIVING_GIFTS.ID))
                    .where(attributes.and(GIVING_REVERSALS.REVERSED_ON.between(f.from(), f.to()))))
                .unionAll(db
                    .select(GIVING_GIFTS.ID, DSL.inline("REFUND"), GIVING_PAYMENT_REFUNDS.REFUNDED_ON,
                        GIVING_GIFTS.DONOR_ID, GIVING_FUNDS.ID, GIVING_FUNDS.NAME, GIVING_GIFTS.CURRENCY,
                        GIVING_PAYMENT_REFUNDS.AMOUNT_MINOR.neg(), GIVING_GIFTS.METHOD,
                        GIVING_PAYMENT_REFUNDS.REFERENCE, DSL.inline("Provider-completed refund"))
                    .from(GIVING_GIFTS)
                    .join(GIVING_PAYMENT_REFUNDS)
                    .on(GIVING_PAYMENT_REFUNDS.GIFT_ID.eq(GIVING_GIFTS.ID))
                    .join(GIVING_ALLOCATIONS)
                    .on(GIVING_ALLOCATIONS.GIFT_ID.eq(GIVING_GIFTS.ID)
                        .and(GIVING_ALLOCATIONS.FUND_ID.eq(GIVING_PAYMENT_REFUNDS.FUND_ID)))
                    .join(GIVING_FUNDS)
                    .on(GIVING_FUNDS.ID.eq(GIVING_PAYMENT_REFUNDS.FUND_ID))
                    .where(attributes.and(GIVING_PAYMENT_REFUNDS.REFUNDED_ON.between(f.from(), f.to()))))
                .orderBy(3, 1, 2, 5)
                .limit(maximum + 1)
                .fetch();
        if (rows.size() > maximum) {
            throw GivingException.conflict("This report exceeds " + maximum
                + " allocation lines. Narrow the date range or choose a donor or fund.");
        }
        Map<UUID, String> names =
            people.getAll(rows.stream().map(r -> r.value4()).filter(Objects::nonNull).distinct().toList())
                .stream()
                .collect(Collectors.toMap(PersonDirectory.Entry::id, PersonDirectory.Entry::name));
        return rows.stream()
            .map(r -> new Line(r.value1(), r.value2(), r.value3(), r.value4(),
                r.value4() == null ? "Anonymous" : names.getOrDefault(r.value4(), "Person unavailable"), r.value5(),
                r.value6(), r.value7(), r.value8(), r.value9(), r.value10(), r.value11()))
            .toList();
    }
}
