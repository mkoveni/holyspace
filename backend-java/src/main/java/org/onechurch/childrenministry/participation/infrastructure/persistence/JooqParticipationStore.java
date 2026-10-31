package org.onechurch.childrenministry.participation.infrastructure.persistence;
import static org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords.time;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_CLASSES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_CLASS_ASSIGNMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_CONSENT_POLICIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ENROLMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_PROGRAMMES;

import org.jooq.DSLContext;
import org.onechurch.childrenministry.participation.application.contract.ParticipationReader;
import org.onechurch.childrenministry.participation.domain.ClassAssignment;
import org.onechurch.childrenministry.participation.domain.Enrolment;
import org.onechurch.childrenministry.participation.domain.MinistryClass;
import org.onechurch.childrenministry.participation.domain.Programme;
import org.onechurch.childrenministry.participation.domain.repository.ParticipationRepository;
import org.onechurch.childrenministry.safeguarding.domain.ConsentPolicy;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenClassAssignmentsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenClassesRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenConsentPoliciesRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenEnrolmentsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenProgrammesRecord;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqParticipationStore implements ParticipationReader, ParticipationRepository {
    @Override
    public void endAssignments(UUID enrolmentId, Instant at) {
        db.update(CHILDREN_CLASS_ASSIGNMENTS)
            .set(CHILDREN_CLASS_ASSIGNMENTS.ENDED_AT, time(at))
            .set(CHILDREN_CLASS_ASSIGNMENTS.VERSION, CHILDREN_CLASS_ASSIGNMENTS.VERSION.plus(1))
            .where(CHILDREN_CLASS_ASSIGNMENTS.ENROLMENT_ID.eq(enrolmentId))
            .and(CHILDREN_CLASS_ASSIGNMENTS.ENDED_AT.isNull())
            .execute();
    }
    @Override
    public List<ClassAssignment> expectedChildren(UUID classId, LocalDate on, int limit, int offset) {
        return db.select(CHILDREN_CLASS_ASSIGNMENTS.fields())
            .from(CHILDREN_CLASS_ASSIGNMENTS)
            .join(CHILDREN_ENROLMENTS)
            .on(CHILDREN_ENROLMENTS.ID.eq(CHILDREN_CLASS_ASSIGNMENTS.ENROLMENT_ID))
            .where(CHILDREN_CLASS_ASSIGNMENTS.CLASS_ID.eq(classId))
            .and(CHILDREN_CLASS_ASSIGNMENTS.ENDED_AT.isNull())
            .and(CHILDREN_CLASS_ASSIGNMENTS.VALID_FROM.le(on))
            .and(CHILDREN_CLASS_ASSIGNMENTS.VALID_UNTIL.gt(on))
            .and(CHILDREN_ENROLMENTS.ENDED_AT.isNull())
            .and(CHILDREN_ENROLMENTS.VALID_FROM.le(on))
            .and(CHILDREN_ENROLMENTS.VALID_UNTIL.gt(on))
            .orderBy(CHILDREN_CLASS_ASSIGNMENTS.CHILD_ID)
            .limit(limit)
            .offset(offset)
            .fetch(row -> ChildrenRecords.classAssignment(row.into(CHILDREN_CLASS_ASSIGNMENTS)));
    }

    private final DSLContext db;
    public JooqParticipationStore(DSLContext db) {
        this.db = db;
    }
    @Override
    public Optional<Programme> programme(UUID id) {
        return db.selectFrom(CHILDREN_PROGRAMMES)
            .where(CHILDREN_PROGRAMMES.ID.eq(id))
            .fetchOptional(ChildrenRecords::programme);
    }
    @Override
    public Optional<Programme> programmeForUpdate(UUID id) {
        return db.selectFrom(CHILDREN_PROGRAMMES)
            .where(CHILDREN_PROGRAMMES.ID.eq(id))
            .forUpdate()
            .fetchOptional(ChildrenRecords::programme);
    }
    @Override
    public void insertProgramme(Programme value) {
        db.insertInto(CHILDREN_PROGRAMMES).set(record(value)).execute();
    }
    @Override
    public void updateProgramme(Programme value) {
        int changed = db.update(CHILDREN_PROGRAMMES)
            .set(record(value))
            .where(CHILDREN_PROGRAMMES.ID.eq(value.id()))
            .and(CHILDREN_PROGRAMMES.VERSION.eq(value.version() - 1))
            .execute();
        if (changed != 1) {
            throw ChildrenException.conflict("Record changed. Reload and try again.");
        }
    }
    private ChildrenProgrammesRecord record(Programme value) {
        ChildrenProgrammesRecord row = db.newRecord(CHILDREN_PROGRAMMES);
        row.setId(value.id());
        row.setName(value.name());
        row.setCurrentPolicyId(value.currentPolicyId());
        row.setMinStaff(value.minStaff());
        row.setChildrenPerStaff(value.childrenPerStaff());
        row.setVersion(value.version());
        row.setCreatedAt(time(value.createdAt()));
        return row;
    }
    @Override
    public Optional<ConsentPolicy> consentPolicy(UUID id) {
        return db.selectFrom(CHILDREN_CONSENT_POLICIES)
            .where(CHILDREN_CONSENT_POLICIES.ID.eq(id))
            .fetchOptional(ChildrenRecords::consentPolicy);
    }
    @Override
    public Optional<ConsentPolicy> consentPolicyForUpdate(UUID id) {
        return db.selectFrom(CHILDREN_CONSENT_POLICIES)
            .where(CHILDREN_CONSENT_POLICIES.ID.eq(id))
            .forUpdate()
            .fetchOptional(ChildrenRecords::consentPolicy);
    }
    @Override
    public void insertConsentPolicy(ConsentPolicy value) {
        db.insertInto(CHILDREN_CONSENT_POLICIES).set(record(value)).execute();
    }
    private ChildrenConsentPoliciesRecord record(ConsentPolicy value) {
        ChildrenConsentPoliciesRecord row = db.newRecord(CHILDREN_CONSENT_POLICIES);
        row.setId(value.id());
        row.setProgrammeId(value.programmeId());
        row.setLabel(value.label());
        row.setDocumentReference(value.documentReference());
        row.setContentHash(value.contentHash());
        row.setCreatedAt(time(value.createdAt()));
        return row;
    }
    @Override
    public Optional<MinistryClass> ministryClass(UUID id) {
        return db.selectFrom(CHILDREN_CLASSES)
            .where(CHILDREN_CLASSES.ID.eq(id))
            .fetchOptional(ChildrenRecords::ministryClass);
    }
    @Override
    public Optional<MinistryClass> ministryClassForUpdate(UUID id) {
        return db.selectFrom(CHILDREN_CLASSES)
            .where(CHILDREN_CLASSES.ID.eq(id))
            .forUpdate()
            .fetchOptional(ChildrenRecords::ministryClass);
    }
    @Override
    public void insertMinistryClass(MinistryClass value) {
        db.insertInto(CHILDREN_CLASSES).set(record(value)).execute();
    }
    private ChildrenClassesRecord record(MinistryClass value) {
        ChildrenClassesRecord row = db.newRecord(CHILDREN_CLASSES);
        row.setId(value.id());
        row.setProgrammeId(value.programmeId());
        row.setName(value.name());
        row.setMinAgeMonths(value.minAgeMonths());
        row.setMaxAgeMonths(value.maxAgeMonths());
        return row;
    }
    @Override
    public Optional<Enrolment> enrolment(UUID id) {
        return db.selectFrom(CHILDREN_ENROLMENTS)
            .where(CHILDREN_ENROLMENTS.ID.eq(id))
            .fetchOptional(ChildrenRecords::enrolment);
    }
    @Override
    public Optional<Enrolment> enrolmentForUpdate(UUID id) {
        return db.selectFrom(CHILDREN_ENROLMENTS)
            .where(CHILDREN_ENROLMENTS.ID.eq(id))
            .forUpdate()
            .fetchOptional(ChildrenRecords::enrolment);
    }
    @Override
    public void insertEnrolment(Enrolment value) {
        db.insertInto(CHILDREN_ENROLMENTS).set(record(value)).execute();
    }
    @Override
    public void updateEnrolment(Enrolment value) {
        int changed = db.update(CHILDREN_ENROLMENTS)
            .set(record(value))
            .where(CHILDREN_ENROLMENTS.ID.eq(value.id()))
            .and(CHILDREN_ENROLMENTS.VERSION.eq(value.version() - 1))
            .execute();
        if (changed != 1) {
            throw ChildrenException.conflict("Record changed. Reload and try again.");
        }
    }
    private ChildrenEnrolmentsRecord record(Enrolment value) {
        ChildrenEnrolmentsRecord row = db.newRecord(CHILDREN_ENROLMENTS);
        row.setId(value.id());
        row.setProgrammeId(value.programmeId());
        row.setChildId(value.childId());
        row.setValidFrom(value.validFrom());
        row.setValidUntil(value.validUntil());
        row.setEndedAt(time(value.endedAt()));
        row.setVersion(value.version());
        row.setCreatedAt(time(value.createdAt()));
        return row;
    }
    @Override
    public Optional<ClassAssignment> classAssignment(UUID id) {
        return db.selectFrom(CHILDREN_CLASS_ASSIGNMENTS)
            .where(CHILDREN_CLASS_ASSIGNMENTS.ID.eq(id))
            .fetchOptional(ChildrenRecords::classAssignment);
    }
    @Override
    public Optional<ClassAssignment> classAssignmentForUpdate(UUID id) {
        return db.selectFrom(CHILDREN_CLASS_ASSIGNMENTS)
            .where(CHILDREN_CLASS_ASSIGNMENTS.ID.eq(id))
            .forUpdate()
            .fetchOptional(ChildrenRecords::classAssignment);
    }
    @Override
    public void insertClassAssignment(ClassAssignment value) {
        db.insertInto(CHILDREN_CLASS_ASSIGNMENTS).set(record(value)).execute();
    }
    @Override
    public void updateClassAssignment(ClassAssignment value) {
        int changed = db.update(CHILDREN_CLASS_ASSIGNMENTS)
            .set(record(value))
            .where(CHILDREN_CLASS_ASSIGNMENTS.ID.eq(value.id()))
            .and(CHILDREN_CLASS_ASSIGNMENTS.VERSION.eq(value.version() - 1))
            .execute();
        if (changed != 1) {
            throw ChildrenException.conflict("Record changed. Reload and try again.");
        }
    }
    private ChildrenClassAssignmentsRecord record(ClassAssignment value) {
        ChildrenClassAssignmentsRecord row = db.newRecord(CHILDREN_CLASS_ASSIGNMENTS);
        row.setId(value.id());
        row.setProgrammeId(value.programmeId());
        row.setClassId(value.classId());
        row.setChildId(value.childId());
        row.setEnrolmentId(value.enrolmentId());
        row.setValidFrom(value.validFrom());
        row.setValidUntil(value.validUntil());
        row.setEndedAt(time(value.endedAt()));
        row.setVersion(value.version());
        return row;
    }

    public List<Programme> programmes(String search, int limit, int offset) {
        return db.selectFrom(CHILDREN_PROGRAMMES)
            .where(CHILDREN_PROGRAMMES.NAME.containsIgnoreCase(search))
            .orderBy(CHILDREN_PROGRAMMES.NAME, CHILDREN_PROGRAMMES.ID)
            .limit(limit)
            .offset(offset)
            .fetch(ChildrenRecords::programme);
    }
    public List<MinistryClass> classes(UUID programmeId) {
        return db.selectFrom(CHILDREN_CLASSES)
            .where(CHILDREN_CLASSES.PROGRAMME_ID.eq(programmeId))
            .orderBy(CHILDREN_CLASSES.NAME, CHILDREN_CLASSES.ID)
            .fetch(ChildrenRecords::ministryClass);
    }
    public List<Enrolment> enrolments(UUID childId, int limit, int offset) {
        return db.selectFrom(CHILDREN_ENROLMENTS)
            .where(CHILDREN_ENROLMENTS.CHILD_ID.eq(childId))
            .orderBy(CHILDREN_ENROLMENTS.CREATED_AT.desc(), CHILDREN_ENROLMENTS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(ChildrenRecords::enrolment);
    }
    public List<ClassAssignment> assignments(UUID childId, int limit, int offset) {
        return db.selectFrom(CHILDREN_CLASS_ASSIGNMENTS)
            .where(CHILDREN_CLASS_ASSIGNMENTS.CHILD_ID.eq(childId))
            .orderBy(CHILDREN_CLASS_ASSIGNMENTS.VALID_FROM.desc(), CHILDREN_CLASS_ASSIGNMENTS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(ChildrenRecords::classAssignment);
    }
    public Optional<Enrolment> enrolmentAt(UUID programmeId, UUID childId, LocalDate on) {
        return db.selectFrom(CHILDREN_ENROLMENTS)
            .where(CHILDREN_ENROLMENTS.CHILD_ID.eq(childId))
            .and(CHILDREN_ENROLMENTS.PROGRAMME_ID.eq(programmeId))
            .and(CHILDREN_ENROLMENTS.ENDED_AT.isNull())
            .and(CHILDREN_ENROLMENTS.VALID_FROM.le(on))
            .and(CHILDREN_ENROLMENTS.VALID_UNTIL.gt(on))
            .fetchOptional(ChildrenRecords::enrolment);
    }
    public Optional<ClassAssignment> assignmentAt(UUID classId, UUID childId, LocalDate on) {
        return db.selectFrom(CHILDREN_CLASS_ASSIGNMENTS)
            .where(CHILDREN_CLASS_ASSIGNMENTS.CHILD_ID.eq(childId))
            .and(CHILDREN_CLASS_ASSIGNMENTS.CLASS_ID.eq(classId))
            .and(CHILDREN_CLASS_ASSIGNMENTS.ENDED_AT.isNull())
            .and(CHILDREN_CLASS_ASSIGNMENTS.VALID_FROM.le(on))
            .and(CHILDREN_CLASS_ASSIGNMENTS.VALID_UNTIL.gt(on))
            .fetchOptional(ChildrenRecords::classAssignment);
    }

}
