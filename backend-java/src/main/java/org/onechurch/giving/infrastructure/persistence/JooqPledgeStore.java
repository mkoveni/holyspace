package org.onechurch.giving.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_ALLOCATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_FUNDS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_GIFTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_HISTORY;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_PAYMENT_REFUNDS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_PLEDGES;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_PLEDGE_ASSIGNMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_REVERSALS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.Field;
import org.jooq.impl.DSL;
import org.onechurch.giving.application.contract.GivingReader;
import org.onechurch.giving.application.contract.PledgeReader;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.Money;
import org.onechurch.giving.domain.Pledge;
import org.onechurch.giving.domain.PledgeAssignment;
import org.onechurch.giving.domain.repository.PledgeRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.infrastructure.jooq.tables.records.GivingPledgeAssignmentsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.GivingPledgesRecord;
import org.springframework.stereotype.Repository;

import java.math.BigDecimal;
import java.time.ZoneOffset;
import java.util.List;
import java.util.Map;
import java.util.Optional;
import java.util.UUID;
import java.util.stream.Collectors;
@Repository
public class JooqPledgeStore implements PledgeRepository, PledgeReader {
    private final DSLContext db;
    private final PersonDirectory people;
    public JooqPledgeStore(DSLContext db, PersonDirectory people) {
        this.db = db;
        this.people = people;
    }
    private Pledge pledge(GivingPledgesRecord r) {
        return new Pledge(r.getId(), r.getDonorId(), r.getFundId(), new Money(r.getAmountMinor(), r.getCurrency()),
            r.getStartsOn(), r.getEndsOn(), r.getStatus(), r.getVersion(), r.getCancellationReason(), r.getCreatedBy(),
            r.getCreatedAt().toInstant());
    }
    private PledgeAssignment assignment(GivingPledgeAssignmentsRecord r) {
        return new PledgeAssignment(r.getId(), r.getPledgeId(), r.getGiftId(), r.getFundId(), r.getDonorId(),
            r.getCurrency(), r.getAmountMinor(), r.getStatus(), r.getVersion(), r.getCancellationReason(),
            r.getCancelledBy(), r.getCancelledAt() == null ? null : r.getCancelledAt().toInstant(), r.getCreatedBy(),
            r.getCreatedAt().toInstant());
    }
    private Optional<UUID> replay(UUID id, String hash, Optional<String> stored) {
        return stored.map(value -> {
            if (!value.equals(hash)) {
                throw GivingException.conflict("This request identifier was used for different details.");
            }
            return id;
        });
    }
    @Override
    public Optional<UUID> pledgeRequest(UUID id, String hash) {
        return replay(id, hash,
            db.select(GIVING_PLEDGES.REQUEST_HASH)
                .from(GIVING_PLEDGES)
                .where(GIVING_PLEDGES.ID.eq(id))
                .fetchOptional(GIVING_PLEDGES.REQUEST_HASH));
    }
    @Override
    public Optional<UUID> assignmentRequest(UUID id, String hash) {
        return replay(id, hash,
            db.select(GIVING_PLEDGE_ASSIGNMENTS.REQUEST_HASH)
                .from(GIVING_PLEDGE_ASSIGNMENTS)
                .where(GIVING_PLEDGE_ASSIGNMENTS.ID.eq(id))
                .fetchOptional(GIVING_PLEDGE_ASSIGNMENTS.REQUEST_HASH));
    }
    @Override
    public void insert(Pledge p, String hash) {
        db.insertInto(GIVING_PLEDGES)
            .set(GIVING_PLEDGES.ID, p.id())
            .set(GIVING_PLEDGES.DONOR_ID, p.donorId())
            .set(GIVING_PLEDGES.FUND_ID, p.fundId())
            .set(GIVING_PLEDGES.AMOUNT_MINOR, p.target().amountMinor())
            .set(GIVING_PLEDGES.CURRENCY, p.target().currency())
            .set(GIVING_PLEDGES.STARTS_ON, p.startsOn())
            .set(GIVING_PLEDGES.ENDS_ON, p.endsOn())
            .set(GIVING_PLEDGES.STATUS, p.status())
            .set(GIVING_PLEDGES.VERSION, p.version())
            .set(GIVING_PLEDGES.REQUEST_HASH, hash)
            .set(GIVING_PLEDGES.CREATED_BY, p.createdBy())
            .set(GIVING_PLEDGES.CREATED_AT, p.createdAt().atOffset(ZoneOffset.UTC))
            .execute();
    }
    @Override
    public Optional<Pledge> lock(UUID id) {
        return db.selectFrom(GIVING_PLEDGES)
            .where(GIVING_PLEDGES.ID.eq(id))
            .forUpdate()
            .fetchOptional()
            .map(this::pledge);
    }
    @Override
    public void save(Pledge p) {
        if (db.update(GIVING_PLEDGES)
            .set(GIVING_PLEDGES.STATUS, p.status())
            .set(GIVING_PLEDGES.VERSION, p.version())
            .set(GIVING_PLEDGES.CANCELLATION_REASON, p.cancellationReason())
            .where(GIVING_PLEDGES.ID.eq(p.id()).and(GIVING_PLEDGES.VERSION.eq(p.version() - 1)))
            .execute() != 1) {
            throw GivingException.conflict("The pledge changed. Refresh before trying again.");
        }
    }
    @Override
    public Optional<PledgeAssignment> assignment(UUID id) {
        return db.selectFrom(GIVING_PLEDGE_ASSIGNMENTS)
            .where(GIVING_PLEDGE_ASSIGNMENTS.ID.eq(id))
            .fetchOptional()
            .map(this::assignment);
    }
    @Override
    public void insert(PledgeAssignment a, String hash) {
        db.insertInto(GIVING_PLEDGE_ASSIGNMENTS)
            .set(GIVING_PLEDGE_ASSIGNMENTS.ID, a.id())
            .set(GIVING_PLEDGE_ASSIGNMENTS.PLEDGE_ID, a.pledgeId())
            .set(GIVING_PLEDGE_ASSIGNMENTS.GIFT_ID, a.giftId())
            .set(GIVING_PLEDGE_ASSIGNMENTS.FUND_ID, a.fundId())
            .set(GIVING_PLEDGE_ASSIGNMENTS.DONOR_ID, a.donorId())
            .set(GIVING_PLEDGE_ASSIGNMENTS.CURRENCY, a.currency())
            .set(GIVING_PLEDGE_ASSIGNMENTS.AMOUNT_MINOR, a.amountMinor())
            .set(GIVING_PLEDGE_ASSIGNMENTS.STATUS, a.status())
            .set(GIVING_PLEDGE_ASSIGNMENTS.VERSION, a.version())
            .set(GIVING_PLEDGE_ASSIGNMENTS.REQUEST_HASH, hash)
            .set(GIVING_PLEDGE_ASSIGNMENTS.CREATED_BY, a.createdBy())
            .set(GIVING_PLEDGE_ASSIGNMENTS.CREATED_AT, a.createdAt().atOffset(ZoneOffset.UTC))
            .execute();
    }
    @Override
    public void save(PledgeAssignment a) {
        if (db.update(GIVING_PLEDGE_ASSIGNMENTS)
            .set(GIVING_PLEDGE_ASSIGNMENTS.STATUS, a.status())
            .set(GIVING_PLEDGE_ASSIGNMENTS.VERSION, a.version())
            .set(GIVING_PLEDGE_ASSIGNMENTS.CANCELLATION_REASON, a.cancellationReason())
            .set(GIVING_PLEDGE_ASSIGNMENTS.CANCELLED_BY, a.cancelledBy())
            .set(GIVING_PLEDGE_ASSIGNMENTS.CANCELLED_AT,
                a.cancelledAt() == null ? null : a.cancelledAt().atOffset(ZoneOffset.UTC))
            .where(GIVING_PLEDGE_ASSIGNMENTS.ID.eq(a.id()).and(GIVING_PLEDGE_ASSIGNMENTS.VERSION.eq(a.version() - 1)))
            .execute() != 1) {
            throw GivingException.conflict("This assignment changed.");
        }
    }
    private Condition notReversed() {
        return DSL.notExists(db.selectOne()
            .from(GIVING_REVERSALS)
            .where(GIVING_REVERSALS.GIFT_ID.eq(GIVING_PLEDGE_ASSIGNMENTS.GIFT_ID)));
    }
    private Field<BigDecimal> creditField() {
        return db.select(DSL.coalesce(DSL.sum(GIVING_PLEDGE_ASSIGNMENTS.AMOUNT_MINOR), BigDecimal.ZERO))
            .from(GIVING_PLEDGE_ASSIGNMENTS)
            .where(GIVING_PLEDGE_ASSIGNMENTS.PLEDGE_ID.eq(GIVING_PLEDGES.ID)
                .and(GIVING_PLEDGE_ASSIGNMENTS.STATUS.eq("ACTIVE"))
                .and(notReversed()))
            .asField();
    }
    @Override
    public long credited(UUID id) {
        return db.select(DSL.coalesce(DSL.sum(GIVING_PLEDGE_ASSIGNMENTS.AMOUNT_MINOR), BigDecimal.ZERO))
            .from(GIVING_PLEDGE_ASSIGNMENTS)
            .where(GIVING_PLEDGE_ASSIGNMENTS.PLEDGE_ID.eq(id)
                .and(GIVING_PLEDGE_ASSIGNMENTS.STATUS.eq("ACTIVE"))
                .and(notReversed()))
            .fetchOne(0, BigDecimal.class)
            .longValueExact();
    }
    @Override
    public long assigned(UUID giftId, UUID fundId) {
        return db.select(DSL.coalesce(DSL.sum(GIVING_PLEDGE_ASSIGNMENTS.AMOUNT_MINOR), BigDecimal.ZERO))
            .from(GIVING_PLEDGE_ASSIGNMENTS)
            .where(GIVING_PLEDGE_ASSIGNMENTS.GIFT_ID.eq(giftId)
                .and(GIVING_PLEDGE_ASSIGNMENTS.FUND_ID.eq(fundId))
                .and(GIVING_PLEDGE_ASSIGNMENTS.STATUS.eq("ACTIVE")))
            .fetchOne(0, BigDecimal.class)
            .longValueExact();
    }
    private List<Summary> summaries(Condition condition, int limit, int offset) {
        Field<BigDecimal> credit = creditField();
        List<? extends org.jooq.Record> rows = db.select(GIVING_PLEDGES.fields())
            .select(GIVING_FUNDS.NAME, credit)
            .from(GIVING_PLEDGES)
            .join(GIVING_FUNDS)
            .on(GIVING_FUNDS.ID.eq(GIVING_PLEDGES.FUND_ID))
            .where(condition)
            .orderBy(GIVING_PLEDGES.ENDS_ON, GIVING_PLEDGES.ID)
            .limit(limit)
            .offset(offset)
            .fetch();
        Map<UUID, String> names =
            people.getAll(rows.stream().map(r -> r.get(GIVING_PLEDGES.DONOR_ID)).distinct().toList())
                .stream()
                .collect(Collectors.toMap(PersonDirectory.Entry::id, PersonDirectory.Entry::name));
        return rows.stream().map(r -> {
            Pledge p = pledge(r.into(GIVING_PLEDGES));
            long amount = r.get(credit).longValueExact();
            return new Summary(p, names.getOrDefault(p.donorId(), "Person unavailable"), r.get(GIVING_FUNDS.NAME),
                amount, p.target().amountMinor() - amount, amount >= p.target().amountMinor());
        }).toList();
    }
    @Override
    public Page list(UUID donor, String status, int limit, int offset) {
        Condition where = (donor == null ? DSL.trueCondition() : GIVING_PLEDGES.DONOR_ID.eq(donor))
            .and(status == null ? DSL.trueCondition() : GIVING_PLEDGES.STATUS.eq(status));
        return new Page(summaries(where, limit, offset), db.fetchCount(GIVING_PLEDGES, where));
    }
    @Override
    public Optional<Detail> detail(UUID id, int limit, int offset) {
        return summaries(GIVING_PLEDGES.ID.eq(id), 1, 0).stream().findFirst().map(summary -> {
            Field<Boolean> reversed = DSL.exists(db.selectOne()
                .from(GIVING_REVERSALS)
                .where(GIVING_REVERSALS.GIFT_ID.eq(GIVING_PLEDGE_ASSIGNMENTS.GIFT_ID)));
            List<Assignment> assignments = db.select(GIVING_PLEDGE_ASSIGNMENTS.fields())
                .select(GIVING_GIFTS.RECEIVED_ON, reversed)
                .from(GIVING_PLEDGE_ASSIGNMENTS)
                .join(GIVING_GIFTS)
                .on(GIVING_GIFTS.ID.eq(GIVING_PLEDGE_ASSIGNMENTS.GIFT_ID))
                .where(GIVING_PLEDGE_ASSIGNMENTS.PLEDGE_ID.eq(id))
                .orderBy(GIVING_PLEDGE_ASSIGNMENTS.CREATED_AT.desc(), GIVING_PLEDGE_ASSIGNMENTS.ID)
                .limit(limit)
                .offset(offset)
                .fetch(r -> new Assignment(assignment(r.into(GIVING_PLEDGE_ASSIGNMENTS)),
                    r.get(GIVING_GIFTS.RECEIVED_ON), r.get(reversed)));
            List<GivingReader.History> history = db.selectFrom(GIVING_HISTORY)
                .where(GIVING_HISTORY.TARGET_TYPE.eq("PLEDGE").and(GIVING_HISTORY.TARGET_ID.eq(id)))
                .orderBy(GIVING_HISTORY.OCCURRED_AT.desc(), GIVING_HISTORY.ID)
                .limit(100)
                .fetch(h -> new GivingReader.History(h.getAction(), h.getReason(), h.getActorId(),
                    h.getOccurredAt().toInstant()));
            return new Detail(summary, assignments, history);
        });
    }
    @Override
    public List<Candidate> candidates(UUID id, int limit, int offset) {
        Pledge p = db.selectFrom(GIVING_PLEDGES)
            .where(GIVING_PLEDGES.ID.eq(id))
            .fetchOptional()
            .map(this::pledge)
            .orElseThrow(GivingException::missing);
        if (!p.status().equals("OPEN")) {
            return List.of();
        }
        Field<Long> used =
            db.select(DSL.coalesce(DSL.sum(GIVING_PLEDGE_ASSIGNMENTS.AMOUNT_MINOR), BigDecimal.ZERO).cast(Long.class))
                .from(GIVING_PLEDGE_ASSIGNMENTS)
                .where(GIVING_PLEDGE_ASSIGNMENTS.GIFT_ID.eq(GIVING_GIFTS.ID)
                    .and(GIVING_PLEDGE_ASSIGNMENTS.FUND_ID.eq(p.fundId()))
                    .and(GIVING_PLEDGE_ASSIGNMENTS.STATUS.eq("ACTIVE")))
                .asField();
        Field<Long> refunded =
            db.select(DSL.coalesce(DSL.sum(GIVING_PAYMENT_REFUNDS.AMOUNT_MINOR), BigDecimal.ZERO).cast(Long.class))
                .from(GIVING_PAYMENT_REFUNDS)
                .where(GIVING_PAYMENT_REFUNDS.GIFT_ID.eq(GIVING_GIFTS.ID))
                .asField();
        Field<Long> available = GIVING_ALLOCATIONS.AMOUNT_MINOR.minus(used).minus(refunded);
        return db
            .select(GIVING_GIFTS.ID, GIVING_GIFTS.RECEIVED_ON, available, GIVING_GIFTS.EXTERNAL_REFERENCE,
                GIVING_GIFTS.METHOD)
            .from(GIVING_GIFTS)
            .join(GIVING_ALLOCATIONS)
            .on(GIVING_ALLOCATIONS.GIFT_ID.eq(GIVING_GIFTS.ID))
            .where(GIVING_GIFTS.DONOR_ID.eq(p.donorId())
                .and(GIVING_GIFTS.CURRENCY.eq(p.target().currency()))
                .and(GIVING_GIFTS.RECEIVED_ON.between(p.startsOn(), p.endsOn()))
                .and(GIVING_ALLOCATIONS.FUND_ID.eq(p.fundId()))
                .and(available.gt(0L))
                .andNotExists(
                    db.selectOne().from(GIVING_REVERSALS).where(GIVING_REVERSALS.GIFT_ID.eq(GIVING_GIFTS.ID))))
            .orderBy(GIVING_GIFTS.RECEIVED_ON, GIVING_GIFTS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(r -> new Candidate(r.value1(), r.value2(), r.value3(), r.value4(), r.value5()));
    }
}
