package org.onechurch.childrenministry.messaging.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_GUARDIAN_COMMUNICATIONS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.childrenministry.messaging.application.contract.GuardianCommunicationReader;
import org.onechurch.childrenministry.messaging.domain.GuardianCommunication;
import org.onechurch.childrenministry.messaging.domain.repository.GuardianCommunicationRepository;
import org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenGuardianCommunicationsRecord;
import org.springframework.stereotype.Repository;

import java.time.LocalDate;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqGuardianCommunicationStore implements GuardianCommunicationReader, GuardianCommunicationRepository {
    private final DSLContext db;
    public JooqGuardianCommunicationStore(DSLContext db) {
        this.db = db;
    }
    private GuardianCommunication map(ChildrenGuardianCommunicationsRecord r) {
        return new GuardianCommunication(r.getId(), r.getChildId(), r.getAuthorityId(), r.getGuardianId(),
            r.getProgrammeId(), r.getPolicyId(), r.getSessionId(), r.getType(), r.getMessage(), r.getDueOn(),
            r.getStatus(), r.getCreatedBy(), ChildrenRecords.instant(r.getCreatedAt()), r.getUpdatedBy(),
            ChildrenRecords.instant(r.getUpdatedAt()), r.getContactReference(), r.getVersion());
    }
    public Optional<GuardianCommunication> lock(UUID id) {
        return db.selectFrom(CHILDREN_GUARDIAN_COMMUNICATIONS)
            .where(CHILDREN_GUARDIAN_COMMUNICATIONS.ID.eq(id))
            .forUpdate()
            .fetchOptional(this::map);
    }
    public Optional<GuardianCommunication> find(UUID id) {
        return db.selectFrom(CHILDREN_GUARDIAN_COMMUNICATIONS)
            .where(CHILDREN_GUARDIAN_COMMUNICATIONS.ID.eq(id))
            .fetchOptional(this::map);
    }
    public Optional<Receipt> receipt(UUID actor, UUID request) {
        return db.select(CHILDREN_GUARDIAN_COMMUNICATIONS.ID, CHILDREN_GUARDIAN_COMMUNICATIONS.REQUEST_HASH)
            .from(CHILDREN_GUARDIAN_COMMUNICATIONS)
            .where(CHILDREN_GUARDIAN_COMMUNICATIONS.CREATED_BY.eq(actor))
            .and(CHILDREN_GUARDIAN_COMMUNICATIONS.REQUEST_ID.eq(request))
            .fetchOptional(r -> new Receipt(r.value1(), r.value2()));
    }
    public void insert(GuardianCommunication c, UUID request, String hash) {
        db.insertInto(CHILDREN_GUARDIAN_COMMUNICATIONS)
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.ID, c.id())
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.CHILD_ID, c.childId())
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.AUTHORITY_ID, c.authorityId())
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.GUARDIAN_ID, c.guardianId())
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.PROGRAMME_ID, c.programmeId())
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.POLICY_ID, c.policyId())
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.SESSION_ID, c.sessionId())
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.TYPE, c.type())
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.MESSAGE, c.message())
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.DUE_ON, c.dueOn())
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.STATUS, c.status())
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.CREATED_BY, c.createdBy())
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.CREATED_AT, ChildrenRecords.time(c.createdAt()))
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.REQUEST_ID, request)
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.REQUEST_HASH, hash)
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.UPDATED_BY, c.updatedBy())
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.UPDATED_AT, ChildrenRecords.time(c.updatedAt()))
            .execute();
    }
    public void update(GuardianCommunication c) {
        db.update(CHILDREN_GUARDIAN_COMMUNICATIONS)
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.STATUS, c.status())
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.CONTACT_REFERENCE, c.contactReference())
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.UPDATED_AT, ChildrenRecords.time(c.updatedAt()))
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.UPDATED_BY, c.updatedBy())
            .set(CHILDREN_GUARDIAN_COMMUNICATIONS.VERSION, c.version())
            .where(CHILDREN_GUARDIAN_COMMUNICATIONS.ID.eq(c.id()))
            .execute();
    }
    public Page list(UUID child, String status, LocalDate on, int limit, int offset) {
        Condition where = child == null ? DSL.noCondition() : CHILDREN_GUARDIAN_COMMUNICATIONS.CHILD_ID.eq(child);
        if (!status.equals("ALL"))
            where = where.and(CHILDREN_GUARDIAN_COMMUNICATIONS.STATUS.eq(status));
        long overdue = db.fetchCount(CHILDREN_GUARDIAN_COMMUNICATIONS,
            where.and(CHILDREN_GUARDIAN_COMMUNICATIONS.STATUS.eq("DRAFT"))
                .and(CHILDREN_GUARDIAN_COMMUNICATIONS.DUE_ON.le(on)));
        return new Page(db
            .select(CHILDREN_GUARDIAN_COMMUNICATIONS.ID, CHILDREN_GUARDIAN_COMMUNICATIONS.CHILD_ID,
                CHILDREN_GUARDIAN_COMMUNICATIONS.GUARDIAN_ID, CHILDREN_GUARDIAN_COMMUNICATIONS.PROGRAMME_ID,
                CHILDREN_GUARDIAN_COMMUNICATIONS.SESSION_ID, CHILDREN_GUARDIAN_COMMUNICATIONS.TYPE,
                CHILDREN_GUARDIAN_COMMUNICATIONS.DUE_ON, CHILDREN_GUARDIAN_COMMUNICATIONS.STATUS,
                CHILDREN_GUARDIAN_COMMUNICATIONS.VERSION)
            .from(CHILDREN_GUARDIAN_COMMUNICATIONS)
            .where(where)
            .orderBy(CHILDREN_GUARDIAN_COMMUNICATIONS.DUE_ON, CHILDREN_GUARDIAN_COMMUNICATIONS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(r -> new Summary(r.value1(), r.value2(), r.value3(), r.value4(), r.value5(), r.value6(), r.value7(),
                r.value8(), r.value9())),
            db.fetchCount(CHILDREN_GUARDIAN_COMMUNICATIONS, where), overdue);
    }
}
