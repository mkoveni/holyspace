package org.onechurch.childrenministry.safeguarding.infrastructure.persistence;
import static org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords.time;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SUPPORT_RECORDS;

import org.jooq.DSLContext;
import org.onechurch.childrenministry.safeguarding.application.contract.SupportReader;
import org.onechurch.childrenministry.safeguarding.domain.SupportRecord;
import org.onechurch.childrenministry.safeguarding.domain.repository.SupportRepository;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenSupportRecordsRecord;
import org.springframework.stereotype.Repository;

import java.time.LocalDate;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqSupportStore implements SupportReader, SupportRepository {
    private final DSLContext db;
    public JooqSupportStore(DSLContext db) {
        this.db = db;
    }
    @Override
    public Optional<SupportRecord> supportRecord(UUID id) {
        return db.selectFrom(CHILDREN_SUPPORT_RECORDS)
            .where(CHILDREN_SUPPORT_RECORDS.CHILD_ID.eq(id))
            .fetchOptional(ChildrenRecords::supportRecord);
    }
    @Override
    public Optional<SupportRecord> supportRecordForUpdate(UUID id) {
        return db.selectFrom(CHILDREN_SUPPORT_RECORDS)
            .where(CHILDREN_SUPPORT_RECORDS.CHILD_ID.eq(id))
            .forUpdate()
            .fetchOptional(ChildrenRecords::supportRecord);
    }
    @Override
    public void insertSupportRecord(SupportRecord value) {
        db.insertInto(CHILDREN_SUPPORT_RECORDS).set(record(value)).execute();
    }
    @Override
    public void updateSupportRecord(SupportRecord value) {
        int changed = db.update(CHILDREN_SUPPORT_RECORDS)
            .set(record(value))
            .where(CHILDREN_SUPPORT_RECORDS.CHILD_ID.eq(value.childId()))
            .and(CHILDREN_SUPPORT_RECORDS.VERSION.eq(value.version() - 1))
            .execute();
        if (changed != 1) {
            throw ChildrenException.conflict("Record changed. Reload and try again.");
        }
    }
    private ChildrenSupportRecordsRecord record(SupportRecord value) {
        ChildrenSupportRecordsRecord row = db.newRecord(CHILDREN_SUPPORT_RECORDS);
        row.setChildId(value.childId());
        row.setAllergies(value.allergies());
        row.setCareInstructions(value.careInstructions());
        row.setRestrictedNotes(value.restrictedNotes());
        row.setReviewOn(value.reviewOn());
        row.setRetainUntil(value.retainUntil());
        row.setVersion(value.version());
        row.setUpdatedAt(time(value.updatedAt()));
        return row;
    }
    public List<UUID> expiredSupport(LocalDate date, int limit) {
        return db.select(CHILDREN_SUPPORT_RECORDS.CHILD_ID)
            .from(CHILDREN_SUPPORT_RECORDS)
            .where(CHILDREN_SUPPORT_RECORDS.RETAIN_UNTIL.lt(date))
            .orderBy(CHILDREN_SUPPORT_RECORDS.CHILD_ID)
            .limit(limit)
            .fetch(CHILDREN_SUPPORT_RECORDS.CHILD_ID);
    }
    public void purge(UUID childId, LocalDate before) {
        db.deleteFrom(CHILDREN_SUPPORT_RECORDS)
            .where(CHILDREN_SUPPORT_RECORDS.CHILD_ID.eq(childId))
            .and(CHILDREN_SUPPORT_RECORDS.RETAIN_UNTIL.lt(before))
            .execute();
    }

}
