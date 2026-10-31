package org.onechurch.childrenministry.safeguarding.infrastructure.persistence;
import static org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords.time;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_CONSENTS;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_GUARDIAN_AUTHORITIES;
import static org.onechurch.shared.infrastructure.jooq.Tables.CHILDREN_PICKUP_AUTHORIZATIONS;

import org.jooq.DSLContext;
import org.onechurch.childrenministry.safeguarding.application.contract.SafeguardingReader;
import org.onechurch.childrenministry.safeguarding.domain.Consent;
import org.onechurch.childrenministry.safeguarding.domain.GuardianAuthority;
import org.onechurch.childrenministry.safeguarding.domain.PickupAuthorization;
import org.onechurch.childrenministry.safeguarding.domain.repository.SafeguardingRepository;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.childrenministry.shared.infrastructure.persistence.ChildrenRecords;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenConsentsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenGuardianAuthoritiesRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.ChildrenPickupAuthorizationsRecord;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Repository
public class JooqSafeguardingStore implements SafeguardingReader, SafeguardingRepository {
    private final DSLContext db;
    public JooqSafeguardingStore(DSLContext db) {
        this.db = db;
    }
    @Override
    public Optional<GuardianAuthority> guardianAuthority(UUID id) {
        return db.selectFrom(CHILDREN_GUARDIAN_AUTHORITIES)
            .where(CHILDREN_GUARDIAN_AUTHORITIES.ID.eq(id))
            .fetchOptional(ChildrenRecords::guardianAuthority);
    }
    @Override
    public Optional<GuardianAuthority> guardianAuthorityForUpdate(UUID id) {
        return db.selectFrom(CHILDREN_GUARDIAN_AUTHORITIES)
            .where(CHILDREN_GUARDIAN_AUTHORITIES.ID.eq(id))
            .forUpdate()
            .fetchOptional(ChildrenRecords::guardianAuthority);
    }
    @Override
    public void insertGuardianAuthority(GuardianAuthority value) {
        db.insertInto(CHILDREN_GUARDIAN_AUTHORITIES).set(record(value)).execute();
    }
    @Override
    public void updateGuardianAuthority(GuardianAuthority value) {
        int changed = db.update(CHILDREN_GUARDIAN_AUTHORITIES)
            .set(record(value))
            .where(CHILDREN_GUARDIAN_AUTHORITIES.ID.eq(value.id()))
            .and(CHILDREN_GUARDIAN_AUTHORITIES.VERSION.eq(value.version() - 1))
            .execute();
        if (changed != 1) {
            throw ChildrenException.conflict("Record changed. Reload and try again.");
        }
    }
    private ChildrenGuardianAuthoritiesRecord record(GuardianAuthority value) {
        ChildrenGuardianAuthoritiesRecord row = db.newRecord(CHILDREN_GUARDIAN_AUTHORITIES);
        row.setId(value.id());
        row.setChildId(value.childId());
        row.setGuardianId(value.guardianId());
        row.setValidFrom(time(value.validFrom()));
        row.setValidUntil(time(value.validUntil()));
        row.setEvidenceReference(value.evidenceReference());
        row.setVerifiedBy(value.verifiedBy());
        row.setRevokedAt(time(value.revokedAt()));
        row.setRevocationReason(value.revocationReason());
        row.setVersion(value.version());
        return row;
    }
    @Override
    public Optional<Consent> consent(UUID id) {
        return db.selectFrom(CHILDREN_CONSENTS)
            .where(CHILDREN_CONSENTS.ID.eq(id))
            .fetchOptional(ChildrenRecords::consent);
    }
    @Override
    public Optional<Consent> consentForUpdate(UUID id) {
        return db.selectFrom(CHILDREN_CONSENTS)
            .where(CHILDREN_CONSENTS.ID.eq(id))
            .forUpdate()
            .fetchOptional(ChildrenRecords::consent);
    }
    @Override
    public void insertConsent(Consent value) {
        db.insertInto(CHILDREN_CONSENTS).set(record(value)).execute();
    }
    @Override
    public void updateConsent(Consent value) {
        int changed = db.update(CHILDREN_CONSENTS)
            .set(record(value))
            .where(CHILDREN_CONSENTS.ID.eq(value.id()))
            .and(CHILDREN_CONSENTS.VERSION.eq(value.version() - 1))
            .execute();
        if (changed != 1) {
            throw ChildrenException.conflict("Record changed. Reload and try again.");
        }
    }
    private ChildrenConsentsRecord record(Consent value) {
        ChildrenConsentsRecord row = db.newRecord(CHILDREN_CONSENTS);
        row.setId(value.id());
        row.setProgrammeId(value.programmeId());
        row.setPolicyId(value.policyId());
        row.setChildId(value.childId());
        row.setAuthorityId(value.authorityId());
        row.setValidFrom(time(value.validFrom()));
        row.setValidUntil(time(value.validUntil()));
        row.setAcceptedAt(time(value.acceptedAt()));
        row.setRecordedBy(value.recordedBy());
        row.setEvidenceReference(value.evidenceReference());
        row.setRevokedAt(time(value.revokedAt()));
        row.setRevocationReason(value.revocationReason());
        row.setVersion(value.version());
        return row;
    }
    @Override
    public Optional<PickupAuthorization> pickupAuthorization(UUID id) {
        return db.selectFrom(CHILDREN_PICKUP_AUTHORIZATIONS)
            .where(CHILDREN_PICKUP_AUTHORIZATIONS.ID.eq(id))
            .fetchOptional(ChildrenRecords::pickupAuthorization);
    }
    @Override
    public Optional<PickupAuthorization> pickupAuthorizationForUpdate(UUID id) {
        return db.selectFrom(CHILDREN_PICKUP_AUTHORIZATIONS)
            .where(CHILDREN_PICKUP_AUTHORIZATIONS.ID.eq(id))
            .forUpdate()
            .fetchOptional(ChildrenRecords::pickupAuthorization);
    }
    @Override
    public void insertPickupAuthorization(PickupAuthorization value) {
        db.insertInto(CHILDREN_PICKUP_AUTHORIZATIONS).set(record(value)).execute();
    }
    @Override
    public void updatePickupAuthorization(PickupAuthorization value) {
        int changed = db.update(CHILDREN_PICKUP_AUTHORIZATIONS)
            .set(record(value))
            .where(CHILDREN_PICKUP_AUTHORIZATIONS.ID.eq(value.id()))
            .and(CHILDREN_PICKUP_AUTHORIZATIONS.VERSION.eq(value.version() - 1))
            .execute();
        if (changed != 1) {
            throw ChildrenException.conflict("Record changed. Reload and try again.");
        }
    }
    private ChildrenPickupAuthorizationsRecord record(PickupAuthorization value) {
        ChildrenPickupAuthorizationsRecord row = db.newRecord(CHILDREN_PICKUP_AUTHORIZATIONS);
        row.setId(value.id());
        row.setProgrammeId(value.programmeId());
        row.setChildId(value.childId());
        row.setCollectorId(value.collectorId());
        row.setAuthorityId(value.authorityId());
        row.setValidFrom(time(value.validFrom()));
        row.setValidUntil(time(value.validUntil()));
        row.setEvidenceReference(value.evidenceReference());
        row.setRecordedBy(value.recordedBy());
        row.setRevokedAt(time(value.revokedAt()));
        row.setRevocationReason(value.revocationReason());
        row.setVersion(value.version());
        return row;
    }

    public List<GuardianAuthority> authorities(UUID childId) {
        return db.selectFrom(CHILDREN_GUARDIAN_AUTHORITIES)
            .where(CHILDREN_GUARDIAN_AUTHORITIES.CHILD_ID.eq(childId))
            .orderBy(CHILDREN_GUARDIAN_AUTHORITIES.VALID_FROM, CHILDREN_GUARDIAN_AUTHORITIES.ID)
            .fetch(ChildrenRecords::guardianAuthority);
    }
    public List<Consent> consents(UUID childId) {
        return db.selectFrom(CHILDREN_CONSENTS)
            .where(CHILDREN_CONSENTS.CHILD_ID.eq(childId))
            .orderBy(CHILDREN_CONSENTS.ACCEPTED_AT, CHILDREN_CONSENTS.ID)
            .fetch(ChildrenRecords::consent);
    }
    public List<PickupAuthorization> authorizations(UUID childId) {
        return db.selectFrom(CHILDREN_PICKUP_AUTHORIZATIONS)
            .where(CHILDREN_PICKUP_AUTHORIZATIONS.CHILD_ID.eq(childId))
            .orderBy(CHILDREN_PICKUP_AUTHORIZATIONS.VALID_FROM, CHILDREN_PICKUP_AUTHORIZATIONS.ID)
            .fetch(ChildrenRecords::pickupAuthorization);
    }
    public List<Consent> validConsents(UUID programmeId, UUID policyId, UUID childId, Instant from, Instant until) {
        return db.selectFrom(CHILDREN_CONSENTS)
            .where(CHILDREN_CONSENTS.PROGRAMME_ID.eq(programmeId))
            .and(CHILDREN_CONSENTS.POLICY_ID.eq(policyId))
            .and(CHILDREN_CONSENTS.CHILD_ID.eq(childId))
            .and(CHILDREN_CONSENTS.REVOKED_AT.isNull())
            .and(CHILDREN_CONSENTS.VALID_FROM.le(time(from)))
            .and(CHILDREN_CONSENTS.VALID_UNTIL.ge(time(until)))
            .orderBy(CHILDREN_CONSENTS.ACCEPTED_AT.desc())
            .fetch(ChildrenRecords::consent);
    }
    public List<PickupAuthorization> validAuthorizations(UUID programmeId, UUID childId, UUID collectorId, Instant at) {
        return db.selectFrom(CHILDREN_PICKUP_AUTHORIZATIONS)
            .where(CHILDREN_PICKUP_AUTHORIZATIONS.PROGRAMME_ID.eq(programmeId))
            .and(CHILDREN_PICKUP_AUTHORIZATIONS.CHILD_ID.eq(childId))
            .and(CHILDREN_PICKUP_AUTHORIZATIONS.COLLECTOR_ID.eq(collectorId))
            .and(CHILDREN_PICKUP_AUTHORIZATIONS.REVOKED_AT.isNull())
            .and(CHILDREN_PICKUP_AUTHORIZATIONS.VALID_FROM.le(time(at)))
            .and(CHILDREN_PICKUP_AUTHORIZATIONS.VALID_UNTIL.ge(time(at)))
            .fetch(ChildrenRecords::pickupAuthorization);
    }

}
