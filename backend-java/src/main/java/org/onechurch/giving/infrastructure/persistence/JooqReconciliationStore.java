package org.onechurch.giving.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_GIFTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_PAYMENT_REFUNDS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_RECONCILIATION_BATCHES;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_RECONCILIATION_ITEMS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_RECONCILIATION_REVIEWS;
import static org.onechurch.shared.infrastructure.jooq.Tables.GIVING_REVERSALS;

import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.giving.application.contract.ReconciliationReader;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.Reconciliation;
import org.onechurch.giving.domain.repository.ReconciliationRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.infrastructure.jooq.tables.GivingReconciliationReviews;
import org.onechurch.shared.infrastructure.jooq.tables.records.GivingReconciliationBatchesRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.GivingReconciliationReviewsRecord;
import org.springframework.stereotype.Repository;

import java.math.BigDecimal;
import java.time.ZoneOffset;
import java.util.List;
import java.util.Map;
import java.util.Optional;
import java.util.UUID;
import java.util.stream.Collectors;
@Repository
public class JooqReconciliationStore implements ReconciliationRepository, ReconciliationReader {
    private final DSLContext db;
    private final PersonDirectory people;
    public JooqReconciliationStore(DSLContext db, PersonDirectory people) {
        this.db = db;
        this.people = people;
    }
    private Reconciliation batch(GivingReconciliationBatchesRecord r, List<UUID> ids) {
        return new Reconciliation(r.getId(), r.getReference(), r.getExpectedMinor(), r.getActualMinor(),
            r.getCurrency(), r.getReconciledOn(), r.getReason(), r.getCreatedBy(), r.getCreatedAt().toInstant(), ids);
    }
    private Reconciliation.Review review(GivingReconciliationReviewsRecord r) {
        return new Reconciliation.Review(r.getId(), r.getBatchId(), r.getVersion(), r.getOutcome(), r.getNote(),
            r.getReversedMinor(), r.getCreatedBy(), r.getCreatedAt().toInstant());
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
    public Optional<UUID> request(UUID id, String hash) {
        return replay(id, hash,
            db.select(GIVING_RECONCILIATION_BATCHES.REQUEST_HASH)
                .from(GIVING_RECONCILIATION_BATCHES)
                .where(GIVING_RECONCILIATION_BATCHES.ID.eq(id))
                .fetchOptional(GIVING_RECONCILIATION_BATCHES.REQUEST_HASH));
    }
    @Override
    public Optional<UUID> reviewRequest(UUID id, String hash) {
        return replay(id, hash,
            db.select(GIVING_RECONCILIATION_REVIEWS.REQUEST_HASH)
                .from(GIVING_RECONCILIATION_REVIEWS)
                .where(GIVING_RECONCILIATION_REVIEWS.ID.eq(id))
                .fetchOptional(GIVING_RECONCILIATION_REVIEWS.REQUEST_HASH));
    }
    @Override
    public void insert(Reconciliation b, String hash) {
        db.insertInto(GIVING_RECONCILIATION_BATCHES)
            .set(GIVING_RECONCILIATION_BATCHES.ID, b.id())
            .set(GIVING_RECONCILIATION_BATCHES.REFERENCE, b.reference())
            .set(GIVING_RECONCILIATION_BATCHES.EXPECTED_MINOR, b.expectedMinor())
            .set(GIVING_RECONCILIATION_BATCHES.ACTUAL_MINOR, b.actualMinor())
            .set(GIVING_RECONCILIATION_BATCHES.CURRENCY, b.currency())
            .set(GIVING_RECONCILIATION_BATCHES.RECONCILED_ON, b.reconciledOn())
            .set(GIVING_RECONCILIATION_BATCHES.REASON, b.reason())
            .set(GIVING_RECONCILIATION_BATCHES.CREATED_BY, b.createdBy())
            .set(GIVING_RECONCILIATION_BATCHES.CREATED_AT, b.createdAt().atOffset(ZoneOffset.UTC))
            .set(GIVING_RECONCILIATION_BATCHES.REQUEST_HASH, hash)
            .execute();
        for (UUID gift : b.giftIds()) {
            db.insertInto(GIVING_RECONCILIATION_ITEMS)
                .set(GIVING_RECONCILIATION_ITEMS.BATCH_ID, b.id())
                .set(GIVING_RECONCILIATION_ITEMS.GIFT_ID, gift)
                .execute();
        }
    }
    @Override
    public Optional<Reconciliation> find(UUID id) {
        return db.selectFrom(GIVING_RECONCILIATION_BATCHES)
            .where(GIVING_RECONCILIATION_BATCHES.ID.eq(id))
            .fetchOptional()
            .map(r -> batch(r,
                db.select(GIVING_RECONCILIATION_ITEMS.GIFT_ID)
                    .from(GIVING_RECONCILIATION_ITEMS)
                    .where(GIVING_RECONCILIATION_ITEMS.BATCH_ID.eq(id))
                    .orderBy(GIVING_RECONCILIATION_ITEMS.GIFT_ID)
                    .fetch(GIVING_RECONCILIATION_ITEMS.GIFT_ID)));
    }
    @Override
    public void lock(UUID id) {
        db.selectFrom(GIVING_RECONCILIATION_BATCHES)
            .where(GIVING_RECONCILIATION_BATCHES.ID.eq(id))
            .forUpdate()
            .fetchOptional()
            .orElseThrow(GivingException::missing);
    }
    @Override
    public long version(UUID id) {
        return db.select(DSL.coalesce(DSL.max(GIVING_RECONCILIATION_REVIEWS.VERSION), 0L))
            .from(GIVING_RECONCILIATION_REVIEWS)
            .where(GIVING_RECONCILIATION_REVIEWS.BATCH_ID.eq(id))
            .fetchOne(0, Long.class);
    }
    @Override
    public long reversedMinor(UUID id) {
        return items(List.of(id)).getOrDefault(id, List.of())
            .stream()
            .mapToLong(item -> item.reversed() ? item.amountMinor() : item.refundedMinor())
            .sum();
    }
    @Override
    public void insert(Reconciliation.Review r, String hash) {
        db.insertInto(GIVING_RECONCILIATION_REVIEWS)
            .set(GIVING_RECONCILIATION_REVIEWS.ID, r.id())
            .set(GIVING_RECONCILIATION_REVIEWS.BATCH_ID, r.batchId())
            .set(GIVING_RECONCILIATION_REVIEWS.VERSION, r.version())
            .set(GIVING_RECONCILIATION_REVIEWS.OUTCOME, r.outcome())
            .set(GIVING_RECONCILIATION_REVIEWS.NOTE, r.note())
            .set(GIVING_RECONCILIATION_REVIEWS.REVERSED_MINOR, r.reversedMinor())
            .set(GIVING_RECONCILIATION_REVIEWS.CREATED_BY, r.createdBy())
            .set(GIVING_RECONCILIATION_REVIEWS.CREATED_AT, r.createdAt().atOffset(ZoneOffset.UTC))
            .set(GIVING_RECONCILIATION_REVIEWS.REQUEST_HASH, hash)
            .execute();
    }
    @Override
    public boolean assigned(UUID gift) {
        return db.fetchExists(GIVING_RECONCILIATION_ITEMS, GIVING_RECONCILIATION_ITEMS.GIFT_ID.eq(gift));
    }
    private Map<UUID, List<Item>> items(List<UUID> ids) {
        org.jooq.Field<Long> refunds =
            db.select(DSL.coalesce(DSL.sum(GIVING_PAYMENT_REFUNDS.AMOUNT_MINOR), BigDecimal.ZERO).cast(Long.class))
                .from(GIVING_PAYMENT_REFUNDS)
                .where(GIVING_PAYMENT_REFUNDS.GIFT_ID.eq(GIVING_GIFTS.ID))
                .asField();
        org.jooq.Result<org.jooq.Record7<UUID, UUID, UUID, java.time.LocalDate, Long, UUID, Long>> rows = db
            .select(GIVING_RECONCILIATION_ITEMS.BATCH_ID, GIVING_GIFTS.ID, GIVING_GIFTS.DONOR_ID,
                GIVING_GIFTS.RECEIVED_ON, GIVING_GIFTS.AMOUNT_MINOR, GIVING_REVERSALS.ID, refunds)
            .from(GIVING_RECONCILIATION_ITEMS)
            .join(GIVING_GIFTS)
            .on(GIVING_GIFTS.ID.eq(GIVING_RECONCILIATION_ITEMS.GIFT_ID))
            .leftJoin(GIVING_REVERSALS)
            .on(GIVING_REVERSALS.GIFT_ID.eq(GIVING_GIFTS.ID))
            .where(GIVING_RECONCILIATION_ITEMS.BATCH_ID.in(ids))
            .orderBy(GIVING_GIFTS.RECEIVED_ON, GIVING_GIFTS.ID)
            .fetch();
        Map<UUID, String> names =
            people.getAll(rows.stream().map(r -> r.value3()).filter(java.util.Objects::nonNull).distinct().toList())
                .stream()
                .collect(Collectors.toMap(PersonDirectory.Entry::id, PersonDirectory.Entry::name));
        return rows.stream()
            .collect(Collectors.groupingBy(r -> r.value1(),
                Collectors.mapping(r -> new Item(r.value2(),
                    r.value3() == null ? "Anonymous" : names.getOrDefault(r.value3(), "Person unavailable"), r.value4(),
                    r.value5(), r.value6() != null, r.value7()), Collectors.toList())));
    }
    private Map<UUID, Reconciliation.Review> latest(List<UUID> ids) {
        GivingReconciliationReviews newer = GIVING_RECONCILIATION_REVIEWS.as("newer_review");
        return db.selectFrom(GIVING_RECONCILIATION_REVIEWS)
            .where(GIVING_RECONCILIATION_REVIEWS.BATCH_ID.in(ids))
            .andNotExists(db.selectOne()
                .from(newer)
                .where(newer.BATCH_ID.eq(GIVING_RECONCILIATION_REVIEWS.BATCH_ID)
                    .and(newer.VERSION.gt(GIVING_RECONCILIATION_REVIEWS.VERSION))))
            .fetch()
            .stream()
            .collect(Collectors.toMap(GivingReconciliationReviewsRecord::getBatchId, this::review));
    }
    private Summary summary(GivingReconciliationBatchesRecord r, List<Item> items, Reconciliation.Review review) {
        Reconciliation b = batch(r, items.stream().map(Item::giftId).toList());
        long reversed =
            items.stream().mapToLong(item -> item.reversed() ? item.amountMinor() : item.refundedMinor()).sum();
        boolean needsReview = review == null ? b.expectedMinor() != b.actualMinor() || reversed > 0
            : !review.outcome().equals("EXPLAINED") || review.reversedMinor() != reversed;
        return new Summary(b, reversed, b.actualMinor() - reversed, review == null ? 0 : review.version(), review,
            needsReview);
    }
    @Override
    public Page list(int limit, int offset) {
        List<GivingReconciliationBatchesRecord> rows = db.selectFrom(GIVING_RECONCILIATION_BATCHES)
            .orderBy(GIVING_RECONCILIATION_BATCHES.RECONCILED_ON.desc(), GIVING_RECONCILIATION_BATCHES.ID)
            .limit(limit)
            .offset(offset)
            .fetch();
        List<UUID> ids = rows.stream().map(GivingReconciliationBatchesRecord::getId).toList();
        Map<UUID, List<Item>> items = items(ids);
        Map<UUID, Reconciliation.Review> reviews = latest(ids);
        return new Page(rows.stream()
            .map(r -> summary(r, items.getOrDefault(r.getId(), List.of()), reviews.get(r.getId())))
            .toList(), db.fetchCount(GIVING_RECONCILIATION_BATCHES));
    }
    @Override
    public Optional<Detail> detail(UUID id) {
        return db.selectFrom(GIVING_RECONCILIATION_BATCHES)
            .where(GIVING_RECONCILIATION_BATCHES.ID.eq(id))
            .fetchOptional()
            .map(r -> {
                List<Item> rows = items(List.of(id)).getOrDefault(id, List.of());
                List<Reconciliation.Review> reviews = db.selectFrom(GIVING_RECONCILIATION_REVIEWS)
                    .where(GIVING_RECONCILIATION_REVIEWS.BATCH_ID.eq(id))
                    .orderBy(GIVING_RECONCILIATION_REVIEWS.VERSION.desc())
                    .limit(100)
                    .fetch(this::review);
                return new Detail(summary(r, rows, reviews.stream().findFirst().orElse(null)), rows, reviews);
            });
    }
}
