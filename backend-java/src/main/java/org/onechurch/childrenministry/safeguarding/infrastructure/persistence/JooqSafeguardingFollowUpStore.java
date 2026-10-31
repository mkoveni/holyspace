package org.onechurch.childrenministry.safeguarding.infrastructure.persistence;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_CONSENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_ENROLMENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_GUARDIAN_AUTHORITIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_PICKUP_AUTHORIZATIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_PROGRAMMES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_SUPPORT_RECORDS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.Field;
import org.jooq.impl.DSL;
import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingFollowUpReader;
import org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords;
import org.springframework.stereotype.Repository;
import org.springframework.transaction.annotation.Isolation;
import org.springframework.transaction.annotation.Transactional;

import java.time.Instant;
import java.time.LocalDate;
import java.time.OffsetDateTime;
import java.util.Collection;
import java.util.List;
import java.util.UUID;
@Repository
public class JooqSafeguardingFollowUpStore implements SafeguardingFollowUpReader {
    private final DSLContext db;
    public JooqSafeguardingFollowUpStore(DSLContext db) {
        this.db = db;
    }
    @Override
    @Transactional(readOnly = true, isolation = Isolation.REPEATABLE_READ)
    public Facts load(UUID programmeId, Collection<UUID> matchingIds, LocalDate on, Instant at, boolean includeCare) {
        Condition guardian = CHILDREN_GUARDIAN_AUTHORITIES.REVOKED_AT.isNull()
            .and(CHILDREN_GUARDIAN_AUTHORITIES.VALID_FROM.le(ChildrenRecords.time(at)));
        Field<OffsetDateTime> guardianUntil = db.select(DSL.max(CHILDREN_GUARDIAN_AUTHORITIES.VALID_UNTIL))
            .from(CHILDREN_GUARDIAN_AUTHORITIES)
            .where(guardian)
            .and(CHILDREN_GUARDIAN_AUTHORITIES.CHILD_ID.eq(CHILDREN_ENROLMENTS.CHILD_ID))
            .asField();
        Field<OffsetDateTime> consentUntil =
            db.select(DSL.max(DSL.least(CHILDREN_CONSENTS.VALID_UNTIL, CHILDREN_GUARDIAN_AUTHORITIES.VALID_UNTIL)))
                .from(CHILDREN_CONSENTS)
                .join(CHILDREN_GUARDIAN_AUTHORITIES)
                .on(CHILDREN_GUARDIAN_AUTHORITIES.ID.eq(CHILDREN_CONSENTS.AUTHORITY_ID))
                .where(guardian)
                .and(CHILDREN_CONSENTS.REVOKED_AT.isNull())
                .and(CHILDREN_CONSENTS.VALID_FROM.le(ChildrenRecords.time(at)))
                .and(CHILDREN_CONSENTS.CHILD_ID.eq(CHILDREN_ENROLMENTS.CHILD_ID))
                .and(CHILDREN_CONSENTS.PROGRAMME_ID.eq(CHILDREN_ENROLMENTS.PROGRAMME_ID))
                .and(CHILDREN_CONSENTS.POLICY_ID.eq(CHILDREN_PROGRAMMES.CURRENT_POLICY_ID))
                .asField();
        Condition where = CHILDREN_ENROLMENTS.ENDED_AT.isNull()
            .and(CHILDREN_ENROLMENTS.VALID_FROM.le(on))
            .and(CHILDREN_ENROLMENTS.VALID_UNTIL.gt(on));
        if (programmeId != null) {
            where = where.and(CHILDREN_ENROLMENTS.PROGRAMME_ID.eq(programmeId));
        }
        if (matchingIds != null) {
            where = where.and(CHILDREN_ENROLMENTS.CHILD_ID.in(matchingIds));
        }
        List<Child> children = db
            .select(CHILDREN_ENROLMENTS.CHILD_ID, CHILDREN_ENROLMENTS.PROGRAMME_ID, CHILDREN_PROGRAMMES.NAME,
                CHILDREN_PROGRAMMES.CURRENT_POLICY_ID, guardianUntil, consentUntil)
            .from(CHILDREN_ENROLMENTS)
            .join(CHILDREN_PROGRAMMES)
            .on(CHILDREN_PROGRAMMES.ID.eq(CHILDREN_ENROLMENTS.PROGRAMME_ID))
            .where(where)
            .orderBy(CHILDREN_ENROLMENTS.CHILD_ID, CHILDREN_ENROLMENTS.PROGRAMME_ID)
            .limit(1001)
            .fetch(row -> new Child(row.value1(), row.value2(), row.value3(), row.value4(),
                ChildrenRecords.instant(row.value5()), ChildrenRecords.instant(row.value6())));
        if (children.isEmpty() || children.size() > 1000) {
            return new Facts(children, List.of(), List.of());
        }
        List<UUID> childIds = children.stream().map(Child::childId).distinct().toList();
        List<UUID> programmes = children.stream().map(Child::programmeId).distinct().toList();
        List<Pickup> pickups = db
            .select(CHILDREN_PICKUP_AUTHORIZATIONS.CHILD_ID, CHILDREN_PICKUP_AUTHORIZATIONS.PROGRAMME_ID,
                CHILDREN_PICKUP_AUTHORIZATIONS.COLLECTOR_ID,
                DSL.max(
                    DSL.least(CHILDREN_PICKUP_AUTHORIZATIONS.VALID_UNTIL, CHILDREN_GUARDIAN_AUTHORITIES.VALID_UNTIL)))
            .from(CHILDREN_PICKUP_AUTHORIZATIONS)
            .join(CHILDREN_GUARDIAN_AUTHORITIES)
            .on(CHILDREN_GUARDIAN_AUTHORITIES.ID.eq(CHILDREN_PICKUP_AUTHORIZATIONS.AUTHORITY_ID))
            .where(guardian)
            .and(CHILDREN_PICKUP_AUTHORIZATIONS.REVOKED_AT.isNull())
            .and(CHILDREN_PICKUP_AUTHORIZATIONS.VALID_FROM.le(ChildrenRecords.time(at)))
            .and(CHILDREN_PICKUP_AUTHORIZATIONS.CHILD_ID.in(childIds))
            .and(CHILDREN_PICKUP_AUTHORIZATIONS.PROGRAMME_ID.in(programmes))
            .groupBy(CHILDREN_PICKUP_AUTHORIZATIONS.CHILD_ID, CHILDREN_PICKUP_AUTHORIZATIONS.PROGRAMME_ID,
                CHILDREN_PICKUP_AUTHORIZATIONS.COLLECTOR_ID)
            .fetch(row -> new Pickup(row.value1(), row.value2(), row.value3(), ChildrenRecords.instant(row.value4())));
        List<Care> care = includeCare ? db.select(CHILDREN_SUPPORT_RECORDS.CHILD_ID, CHILDREN_SUPPORT_RECORDS.REVIEW_ON)
            .from(CHILDREN_SUPPORT_RECORDS)
            .where(CHILDREN_SUPPORT_RECORDS.CHILD_ID.in(childIds))
            .and(CHILDREN_SUPPORT_RECORDS.RETAIN_UNTIL.ge(on))
            .fetch(row -> new Care(row.value1(), row.value2())) : List.of();
        return new Facts(children, pickups, care);
    }
}
