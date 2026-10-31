package org.onechurch.childrenministry.safeguarding.infrastructure.persistence;
import static org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords.time;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_CLASS_ASSIGNMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_CONSENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ENROLMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_GUARDIAN_AUTHORITIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_PICKUP_AUTHORIZATIONS;

import org.jooq.DSLContext;
import org.onechurch.childrenministry.safeguarding.application.contract.ReadinessReader;
import org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.time.LocalDate;
import java.util.Collection;
import java.util.UUID;
@Repository
public class JooqReadinessStore implements ReadinessReader {
    private final DSLContext db;
    public JooqReadinessStore(DSLContext db) {
        this.db = db;
    }
    public Facts load(UUID programmeId, UUID policyId, Collection<UUID> childIds, LocalDate on, Instant at) {
        return new Facts(
            db.selectFrom(CHILDREN_ENROLMENTS)
                .where(CHILDREN_ENROLMENTS.PROGRAMME_ID.eq(programmeId))
                .and(CHILDREN_ENROLMENTS.CHILD_ID.in(childIds))
                .and(CHILDREN_ENROLMENTS.ENDED_AT.isNull())
                .and(CHILDREN_ENROLMENTS.VALID_FROM.le(on))
                .and(CHILDREN_ENROLMENTS.VALID_UNTIL.gt(on))
                .fetch(ChildrenRecords::enrolment),
            db.selectFrom(CHILDREN_CLASS_ASSIGNMENTS)
                .where(CHILDREN_CLASS_ASSIGNMENTS.PROGRAMME_ID.eq(programmeId))
                .and(CHILDREN_CLASS_ASSIGNMENTS.CHILD_ID.in(childIds))
                .and(CHILDREN_CLASS_ASSIGNMENTS.ENDED_AT.isNull())
                .and(CHILDREN_CLASS_ASSIGNMENTS.VALID_FROM.le(on))
                .and(CHILDREN_CLASS_ASSIGNMENTS.VALID_UNTIL.gt(on))
                .fetch(ChildrenRecords::classAssignment),
            db.selectFrom(CHILDREN_GUARDIAN_AUTHORITIES)
                .where(CHILDREN_GUARDIAN_AUTHORITIES.CHILD_ID.in(childIds))
                .and(CHILDREN_GUARDIAN_AUTHORITIES.REVOKED_AT.isNull())
                .and(CHILDREN_GUARDIAN_AUTHORITIES.VALID_FROM.le(time(at)))
                .and(CHILDREN_GUARDIAN_AUTHORITIES.VALID_UNTIL.gt(time(at)))
                .fetch(ChildrenRecords::guardianAuthority),
            db.selectFrom(CHILDREN_CONSENTS)
                .where(CHILDREN_CONSENTS.PROGRAMME_ID.eq(programmeId))
                .and(CHILDREN_CONSENTS.POLICY_ID.eq(policyId))
                .and(CHILDREN_CONSENTS.CHILD_ID.in(childIds))
                .and(CHILDREN_CONSENTS.REVOKED_AT.isNull())
                .and(CHILDREN_CONSENTS.VALID_FROM.le(time(at)))
                .and(CHILDREN_CONSENTS.VALID_UNTIL.gt(time(at)))
                .fetch(ChildrenRecords::consent),
            db.selectFrom(CHILDREN_PICKUP_AUTHORIZATIONS)
                .where(CHILDREN_PICKUP_AUTHORIZATIONS.PROGRAMME_ID.eq(programmeId))
                .and(CHILDREN_PICKUP_AUTHORIZATIONS.CHILD_ID.in(childIds))
                .and(CHILDREN_PICKUP_AUTHORIZATIONS.REVOKED_AT.isNull())
                .and(CHILDREN_PICKUP_AUTHORIZATIONS.VALID_FROM.le(time(at)))
                .and(CHILDREN_PICKUP_AUTHORIZATIONS.VALID_UNTIL.gt(time(at)))
                .fetch(ChildrenRecords::pickupAuthorization));
    }
}
