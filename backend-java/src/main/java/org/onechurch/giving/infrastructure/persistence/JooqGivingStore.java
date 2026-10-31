package org.onechurch.giving.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_ALLOCATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_FUNDS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_GIFTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_HISTORY;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_PAYMENT_REFUNDS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_RECONCILIATION_ITEMS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_REVERSALS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.giving.application.contract.GivingReader;
import org.onechurch.giving.domain.Fund;
import org.onechurch.giving.domain.Gift;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.Money;
import org.onechurch.giving.domain.repository.GivingRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.infrastructure.jooq.tables.records.GivingFundsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.GivingGiftsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.GivingReversalsRecord;
import org.springframework.stereotype.Repository;

import java.math.BigDecimal;
import java.time.Instant;
import java.time.ZoneOffset;
import java.util.HashMap;
import java.util.List;
import java.util.Map;
import java.util.Optional;
import java.util.UUID;
import java.util.stream.Collectors;
@Repository
public class JooqGivingStore implements GivingReader, GivingRepository {
    private final DSLContext db;
    private final PersonDirectory people;
    public JooqGivingStore(DSLContext db, PersonDirectory people) {
        this.db = db;
        this.people = people;
    }
    private Fund fund(GivingFundsRecord r) {
        return new Fund(r.getId(), r.getName(), r.getDescription(), r.getActive(), r.getVersion());
    }
    private Gift gift(GivingGiftsRecord r) {
        List<Gift.Allocation> allocations = db.selectFrom(GIVING_ALLOCATIONS)
            .where(GIVING_ALLOCATIONS.GIFT_ID.eq(r.getId()))
            .orderBy(GIVING_ALLOCATIONS.FUND_ID)
            .fetch(a -> new Gift.Allocation(a.getFundId(), a.getAmountMinor()));
        return new Gift(r.getId(), r.getDonorId(), new Money(r.getAmountMinor(), r.getCurrency()), r.getReceivedOn(),
            r.getMethod(), r.getExternalReference(), allocations, r.getCreatedBy(), r.getCreatedAt().toInstant());
    }
    private Gift.Reversal reversal(GivingReversalsRecord r) {
        return new Gift.Reversal(r.getId(), r.getGiftId(), r.getReversedOn(), r.getReason(), r.getCreatedBy(),
            r.getCreatedAt().toInstant());
    }
    @Override
    public Optional<Fund> lockFund(UUID id) {
        return db.selectFrom(GIVING_FUNDS).where(GIVING_FUNDS.ID.eq(id)).forUpdate().fetchOptional().map(this::fund);
    }
    @Override
    public Optional<Gift> lockGift(UUID id) {
        return db.selectFrom(GIVING_GIFTS).where(GIVING_GIFTS.ID.eq(id)).forUpdate().fetchOptional().map(this::gift);
    }
    private Optional<UUID> request(UUID id, String hash, Optional<String> saved) {
        return saved.map(value -> {
            if (!value.equals(hash)) {
                throw GivingException.conflict("This request identifier was already used for different details.");
            }
            return id;
        });
    }
    @Override
    public Optional<UUID> fundRequest(UUID id, String hash) {
        return request(id, hash,
            db.select(GIVING_FUNDS.REQUEST_HASH)
                .from(GIVING_FUNDS)
                .where(GIVING_FUNDS.ID.eq(id))
                .fetchOptional(GIVING_FUNDS.REQUEST_HASH));
    }
    @Override
    public Optional<UUID> giftRequest(UUID id, String hash) {
        return request(id, hash,
            db.select(GIVING_GIFTS.REQUEST_HASH)
                .from(GIVING_GIFTS)
                .where(GIVING_GIFTS.ID.eq(id))
                .fetchOptional(GIVING_GIFTS.REQUEST_HASH));
    }
    @Override
    public Optional<UUID> reversalRequest(UUID id, String hash) {
        return request(id, hash,
            db.select(GIVING_REVERSALS.REQUEST_HASH)
                .from(GIVING_REVERSALS)
                .where(GIVING_REVERSALS.ID.eq(id))
                .fetchOptional(GIVING_REVERSALS.REQUEST_HASH));
    }
    @Override
    public boolean insertFund(Fund fund, String hash) {
        return db.insertInto(GIVING_FUNDS)
            .set(GIVING_FUNDS.ID, fund.id())
            .set(GIVING_FUNDS.NAME, fund.name())
            .set(GIVING_FUNDS.DESCRIPTION, fund.description())
            .set(GIVING_FUNDS.ACTIVE, fund.active())
            .set(GIVING_FUNDS.VERSION, fund.version())
            .set(GIVING_FUNDS.REQUEST_HASH, hash)
            .onConflictDoNothing()
            .execute() == 1;
    }
    @Override
    public void saveFund(Fund fund) {
        int changed = db.update(GIVING_FUNDS)
            .set(GIVING_FUNDS.NAME, fund.name())
            .set(GIVING_FUNDS.DESCRIPTION, fund.description())
            .set(GIVING_FUNDS.ACTIVE, fund.active())
            .set(GIVING_FUNDS.VERSION, fund.version())
            .where(GIVING_FUNDS.ID.eq(fund.id()).and(GIVING_FUNDS.VERSION.eq(fund.version() - 1)))
            .execute();
        if (changed != 1) {
            throw GivingException.conflict("The fund changed. Refresh before trying again.");
        }
    }
    @Override
    public void insertGift(Gift gift, String hash) {
        db.insertInto(GIVING_GIFTS)
            .set(GIVING_GIFTS.ID, gift.id())
            .set(GIVING_GIFTS.DONOR_ID, gift.donorId())
            .set(GIVING_GIFTS.AMOUNT_MINOR, gift.money().amountMinor())
            .set(GIVING_GIFTS.CURRENCY, gift.money().currency())
            .set(GIVING_GIFTS.RECEIVED_ON, gift.receivedOn())
            .set(GIVING_GIFTS.METHOD, gift.method())
            .set(GIVING_GIFTS.EXTERNAL_REFERENCE, gift.externalReference())
            .set(GIVING_GIFTS.REQUEST_HASH, hash)
            .set(GIVING_GIFTS.CREATED_BY, gift.createdBy())
            .set(GIVING_GIFTS.CREATED_AT, gift.createdAt().atOffset(ZoneOffset.UTC))
            .execute();
        for (Gift.Allocation a : gift.allocations()) {
            db.insertInto(GIVING_ALLOCATIONS)
                .set(GIVING_ALLOCATIONS.GIFT_ID, gift.id())
                .set(GIVING_ALLOCATIONS.FUND_ID, a.fundId())
                .set(GIVING_ALLOCATIONS.AMOUNT_MINOR, a.amountMinor())
                .execute();
        }
    }
    @Override
    public boolean reversed(UUID giftId) {
        return db.fetchExists(GIVING_REVERSALS, GIVING_REVERSALS.GIFT_ID.eq(giftId));
    }
    @Override
    public long refunded(UUID giftId) {
        return db.select(DSL.coalesce(DSL.sum(GIVING_PAYMENT_REFUNDS.AMOUNT_MINOR), BigDecimal.ZERO))
            .from(GIVING_PAYMENT_REFUNDS)
            .where(GIVING_PAYMENT_REFUNDS.GIFT_ID.eq(giftId))
            .fetchOne(0, BigDecimal.class)
            .longValueExact();
    }
    @Override
    public void insertReversal(Gift.Reversal r, String hash) {
        db.insertInto(GIVING_REVERSALS)
            .set(GIVING_REVERSALS.ID, r.id())
            .set(GIVING_REVERSALS.GIFT_ID, r.giftId())
            .set(GIVING_REVERSALS.REVERSED_ON, r.reversedOn())
            .set(GIVING_REVERSALS.REASON, r.reason())
            .set(GIVING_REVERSALS.REQUEST_HASH, hash)
            .set(GIVING_REVERSALS.CREATED_BY, r.createdBy())
            .set(GIVING_REVERSALS.CREATED_AT, r.createdAt().atOffset(ZoneOffset.UTC))
            .execute();
    }
    @Override
    public void audit(UUID target, String targetType, String action, String reason, UUID actor, Instant at) {
        db.insertInto(GIVING_HISTORY)
            .set(GIVING_HISTORY.ID, UUID.randomUUID())
            .set(GIVING_HISTORY.TARGET_ID, target)
            .set(GIVING_HISTORY.TARGET_TYPE, targetType)
            .set(GIVING_HISTORY.ACTION, action)
            .set(GIVING_HISTORY.REASON, reason)
            .set(GIVING_HISTORY.ACTOR_ID, actor)
            .set(GIVING_HISTORY.OCCURRED_AT, at.atOffset(ZoneOffset.UTC))
            .execute();
    }
    @Override
    public FundPage funds(boolean archived, String search, int limit, int offset) {
        Condition where =
            (archived ? DSL.trueCondition() : GIVING_FUNDS.ACTIVE.isTrue()).and(search == null || search.isBlank()
                ? DSL.trueCondition() : GIVING_FUNDS.NAME.containsIgnoreCase(search.trim()));
        return new FundPage(db.selectFrom(GIVING_FUNDS)
            .where(where)
            .orderBy(GIVING_FUNDS.NAME, GIVING_FUNDS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(this::fund), db.fetchCount(GIVING_FUNDS, where));
    }
    private Condition attributes(Filter f) {
        return (f.currency() == null ? DSL.trueCondition() : GIVING_GIFTS.CURRENCY.eq(f.currency()))
            .and(f.donorId() == null ? DSL.trueCondition() : GIVING_GIFTS.DONOR_ID.eq(f.donorId()));
    }
    @Override
    public GiftPage gifts(Filter f) {
        Condition where = attributes(f).and(GIVING_GIFTS.RECEIVED_ON.between(f.from(), f.to()))
            .and(f.fundId() == null ? DSL.trueCondition() : DSL.exists(db.selectOne()
                .from(GIVING_ALLOCATIONS)
                .where(GIVING_ALLOCATIONS.GIFT_ID.eq(GIVING_GIFTS.ID).and(GIVING_ALLOCATIONS.FUND_ID.eq(f.fundId())))));
        List<GivingGiftsRecord> rows = db.selectFrom(GIVING_GIFTS)
            .where(where)
            .orderBy(GIVING_GIFTS.RECEIVED_ON.desc(), GIVING_GIFTS.ID.desc())
            .limit(f.limit())
            .offset(f.offset())
            .fetch();
        List<UUID> ids = rows.stream().map(GivingGiftsRecord::getId).toList();
        java.util.Set<UUID> reversedIds = db.select(GIVING_REVERSALS.GIFT_ID)
            .from(GIVING_REVERSALS)
            .where(GIVING_REVERSALS.GIFT_ID.in(ids))
            .fetchSet(GIVING_REVERSALS.GIFT_ID);
        java.util.Set<UUID> reconciledIds = db.select(GIVING_RECONCILIATION_ITEMS.GIFT_ID)
            .from(GIVING_RECONCILIATION_ITEMS)
            .where(GIVING_RECONCILIATION_ITEMS.GIFT_ID.in(ids))
            .fetchSet(GIVING_RECONCILIATION_ITEMS.GIFT_ID);
        Map<UUID, String> names = people
            .getAll(
                rows.stream().map(GivingGiftsRecord::getDonorId).filter(java.util.Objects::nonNull).distinct().toList())
            .stream()
            .collect(Collectors.toMap(PersonDirectory.Entry::id, PersonDirectory.Entry::name));
        List<GiftSummary> items = rows.stream()
            .map(r -> new GiftSummary(r.getId(), r.getDonorId(),
                r.getDonorId() == null ? "Anonymous" : names.getOrDefault(r.getDonorId(), "Person unavailable"),
                r.getAmountMinor(), r.getCurrency(), r.getReceivedOn(), r.getMethod(), r.getExternalReference(),
                reversedIds.contains(r.getId()), reconciledIds.contains(r.getId()), refunded(r.getId())))
            .toList();
        return new GiftPage(items, db.fetchCount(GIVING_GIFTS, where));
    }
    @Override
    public Optional<Detail> detail(UUID id) {
        return db.selectFrom(GIVING_GIFTS).where(GIVING_GIFTS.ID.eq(id)).fetchOptional().map(r -> {
            Gift gift = gift(r);
            String donor = gift.donorId() == null ? "Anonymous"
                : people.get(gift.donorId()).map(PersonDirectory.Entry::name).orElse("Person unavailable");
            Map<UUID, String> funds = db.select(GIVING_FUNDS.ID, GIVING_FUNDS.NAME)
                .from(GIVING_FUNDS)
                .where(GIVING_FUNDS.ID.in(gift.allocations().stream().map(Gift.Allocation::fundId).toList()))
                .fetchMap(GIVING_FUNDS.ID, GIVING_FUNDS.NAME);
            Gift.Reversal reverse = db.selectFrom(GIVING_REVERSALS)
                .where(GIVING_REVERSALS.GIFT_ID.eq(id))
                .fetchOptional()
                .map(this::reversal)
                .orElse(null);
            List<History> history = db.selectFrom(GIVING_HISTORY)
                .where(GIVING_HISTORY.TARGET_ID.eq(id).and(GIVING_HISTORY.TARGET_TYPE.eq("GIFT")))
                .orderBy(GIVING_HISTORY.OCCURRED_AT, GIVING_HISTORY.ID)
                .fetch(h -> new History(h.getAction(), h.getReason(), h.getActorId(), h.getOccurredAt().toInstant()));
            return new Detail(gift, donor, funds, reverse, history, refunded(id));
        });
    }
    @Override
    public List<Total> totals(Filter f) {
        Condition where =
            attributes(f).and(f.fundId() == null ? DSL.trueCondition() : GIVING_ALLOCATIONS.FUND_ID.eq(f.fundId()));
        org.jooq.Result<org.jooq.Record3<String, BigDecimal, BigDecimal>> entries = db
            .select(GIVING_GIFTS.CURRENCY, DSL.sum(GIVING_ALLOCATIONS.AMOUNT_MINOR), DSL.inline(BigDecimal.ZERO))
            .from(GIVING_GIFTS)
            .join(GIVING_ALLOCATIONS)
            .on(GIVING_ALLOCATIONS.GIFT_ID.eq(GIVING_GIFTS.ID))
            .where(where.and(GIVING_GIFTS.RECEIVED_ON.between(f.from(), f.to())))
            .groupBy(GIVING_GIFTS.CURRENCY)
            .unionAll(
                db.select(GIVING_GIFTS.CURRENCY, DSL.inline(BigDecimal.ZERO), DSL.sum(GIVING_ALLOCATIONS.AMOUNT_MINOR))
                    .from(GIVING_GIFTS)
                    .join(GIVING_ALLOCATIONS)
                    .on(GIVING_ALLOCATIONS.GIFT_ID.eq(GIVING_GIFTS.ID))
                    .join(GIVING_REVERSALS)
                    .on(GIVING_REVERSALS.GIFT_ID.eq(GIVING_GIFTS.ID))
                    .where(where.and(GIVING_REVERSALS.REVERSED_ON.between(f.from(), f.to())))
                    .groupBy(GIVING_GIFTS.CURRENCY))
            .unionAll(db
                .select(GIVING_GIFTS.CURRENCY, DSL.inline(BigDecimal.ZERO),
                    DSL.sum(GIVING_PAYMENT_REFUNDS.AMOUNT_MINOR))
                .from(GIVING_GIFTS)
                .join(GIVING_PAYMENT_REFUNDS)
                .on(GIVING_PAYMENT_REFUNDS.GIFT_ID.eq(GIVING_GIFTS.ID))
                .join(GIVING_ALLOCATIONS)
                .on(GIVING_ALLOCATIONS.GIFT_ID.eq(GIVING_GIFTS.ID)
                    .and(GIVING_ALLOCATIONS.FUND_ID.eq(GIVING_PAYMENT_REFUNDS.FUND_ID)))
                .where(where.and(GIVING_PAYMENT_REFUNDS.REFUNDED_ON.between(f.from(), f.to())))
                .groupBy(GIVING_GIFTS.CURRENCY))
            .fetch();
        Map<String, BigDecimal> gross = new HashMap<>(), reversals = new HashMap<>();
        for (org.jooq.Record3<String, BigDecimal, BigDecimal> entry : entries) {
            gross.merge(entry.value1(), entry.value2(), BigDecimal::add);
            reversals.merge(entry.value1(), entry.value3(), BigDecimal::add);
        }
        Map<String, BigDecimal> currencies = new HashMap<>(gross);
        currencies.putAll(reversals);
        return currencies.keySet().stream().sorted().map(code -> {
            BigDecimal g = gross.getOrDefault(code, BigDecimal.ZERO), r = reversals.getOrDefault(code, BigDecimal.ZERO);
            return new Total(code, g.toBigIntegerExact().toString(), r.toBigIntegerExact().toString(),
                g.subtract(r).toBigIntegerExact().toString());
        }).toList();
    }
}
