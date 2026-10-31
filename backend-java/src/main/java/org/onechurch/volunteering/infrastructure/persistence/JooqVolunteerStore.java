package org.onechurch.volunteering.infrastructure.persistence;

import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_AVAILABILITY_EXCEPTIONS;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_AVAILABILITY_RULES;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_CREDENTIALS;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_CREDENTIAL_TYPES;
import static org.onechurch.shared.infrastructure.jooq.Tables.SERVING_VOLUNTEERS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.volunteering.application.contract.VolunteerReader;
import org.onechurch.volunteering.domain.AvailabilityException;
import org.onechurch.volunteering.domain.AvailabilityRule;
import org.onechurch.volunteering.domain.Credential;
import org.onechurch.volunteering.domain.CredentialType;
import org.onechurch.volunteering.domain.Volunteer;
import org.onechurch.volunteering.domain.repository.VolunteerRepository;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;

@Repository
public class JooqVolunteerStore implements VolunteerReader, VolunteerRepository {
    private final DSLContext db;
    private final PersonDirectory people;
    public JooqVolunteerStore(DSLContext db, PersonDirectory people) {
        this.db = db;
        this.people = people;
    }
    public Optional<Volunteer> volunteer(UUID id) {
        return db.selectFrom(SERVING_VOLUNTEERS)
            .where(SERVING_VOLUNTEERS.ID.eq(id))
            .fetchOptional(ServingRecords::volunteer);
    }
    public Optional<Volunteer> byPerson(UUID id) {
        return db.selectFrom(SERVING_VOLUNTEERS)
            .where(SERVING_VOLUNTEERS.PERSON_ID.eq(id))
            .fetchOptional(ServingRecords::volunteer);
    }
    public Optional<Volunteer> byAccount(UUID id) {
        return db.selectFrom(SERVING_VOLUNTEERS)
            .where(SERVING_VOLUNTEERS.ACCOUNT_ID.eq(id))
            .fetchOptional(ServingRecords::volunteer);
    }
    public Optional<Volunteer> lock(UUID id) {
        return db.selectFrom(SERVING_VOLUNTEERS)
            .where(SERVING_VOLUNTEERS.ID.eq(id))
            .forUpdate()
            .fetchOptional(ServingRecords::volunteer);
    }
    private Condition matching(String search) {
        return search.isBlank() ? DSL.noCondition() : SERVING_VOLUNTEERS.PERSON_ID.in(people.matchingIds(search));
    }
    public List<Volunteer> volunteers(String search, int limit, int offset) {
        return db.selectFrom(SERVING_VOLUNTEERS)
            .where(matching(search))
            .orderBy(SERVING_VOLUNTEERS.CREATED_AT.desc(), SERVING_VOLUNTEERS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(ServingRecords::volunteer);
    }
    public long volunteerCount(String search) {
        return db.fetchCount(SERVING_VOLUNTEERS, matching(search));
    }
    public List<CredentialType> credentialTypes() {
        return db.selectFrom(SERVING_CREDENTIAL_TYPES)
            .orderBy(SERVING_CREDENTIAL_TYPES.NAME, SERVING_CREDENTIAL_TYPES.ID)
            .limit(500)
            .fetch(ServingRecords::credentialType);
    }
    public List<Credential> credentials(UUID id) {
        return db.selectFrom(SERVING_CREDENTIALS)
            .where(SERVING_CREDENTIALS.VOLUNTEER_ID.eq(id))
            .orderBy(SERVING_CREDENTIALS.VERIFIED_AT.desc())
            .limit(500)
            .fetch(ServingRecords::credential);
    }
    public List<AvailabilityRule> availability(UUID id, long revision) {
        return db.selectFrom(SERVING_AVAILABILITY_RULES)
            .where(SERVING_AVAILABILITY_RULES.VOLUNTEER_ID.eq(id).and(SERVING_AVAILABILITY_RULES.REVISION.eq(revision)))
            .orderBy(SERVING_AVAILABILITY_RULES.DAY_OF_WEEK, SERVING_AVAILABILITY_RULES.STARTS_AT)
            .limit(100)
            .fetch(ServingRecords::availabilityRule);
    }
    public List<AvailabilityException> exceptions(UUID id, long revision) {
        return db.selectFrom(SERVING_AVAILABILITY_EXCEPTIONS)
            .where(SERVING_AVAILABILITY_EXCEPTIONS.VOLUNTEER_ID.eq(id)
                .and(SERVING_AVAILABILITY_EXCEPTIONS.REVISION.eq(revision)))
            .orderBy(SERVING_AVAILABILITY_EXCEPTIONS.STARTS_AT)
            .limit(100)
            .fetch(ServingRecords::availabilityException);
    }
    public void revokeCredential(UUID id, Instant at) {
        db.update(SERVING_CREDENTIALS)
            .set(SERVING_CREDENTIALS.REVOKED_AT, ServingRecords.time(at))
            .where(SERVING_CREDENTIALS.ID.eq(id))
            .and(SERVING_CREDENTIALS.REVOKED_AT.isNull())
            .execute();
    }
    public void insert(Volunteer v) {
        db.insertInto(SERVING_VOLUNTEERS)
            .set(SERVING_VOLUNTEERS.ID, v.id())
            .set(SERVING_VOLUNTEERS.PERSON_ID, v.personId())
            .set(SERVING_VOLUNTEERS.ACCOUNT_ID, v.accountId())
            .set(SERVING_VOLUNTEERS.STATUS, v.status())
            .set(SERVING_VOLUNTEERS.AVAILABILITY_REVISION, v.availabilityRevision())
            .set(SERVING_VOLUNTEERS.VERSION, v.version())
            .set(SERVING_VOLUNTEERS.CREATED_AT, ServingRecords.time(v.createdAt()))
            .set(SERVING_VOLUNTEERS.UPDATED_AT, ServingRecords.time(v.updatedAt()))
            .execute();
    }
    public void save(Volunteer v) {
        db.update(SERVING_VOLUNTEERS)
            .set(SERVING_VOLUNTEERS.PERSON_ID, v.personId())
            .set(SERVING_VOLUNTEERS.ACCOUNT_ID, v.accountId())
            .set(SERVING_VOLUNTEERS.STATUS, v.status())
            .set(SERVING_VOLUNTEERS.AVAILABILITY_REVISION, v.availabilityRevision())
            .set(SERVING_VOLUNTEERS.VERSION, v.version())
            .set(SERVING_VOLUNTEERS.CREATED_AT, ServingRecords.time(v.createdAt()))
            .set(SERVING_VOLUNTEERS.UPDATED_AT, ServingRecords.time(v.updatedAt()))
            .where(SERVING_VOLUNTEERS.ID.eq(v.id()))
            .execute();
    }
    public void addCredentialType(CredentialType v) {
        db.insertInto(SERVING_CREDENTIAL_TYPES)
            .set(SERVING_CREDENTIAL_TYPES.ID, v.id())
            .set(SERVING_CREDENTIAL_TYPES.CODE, v.code())
            .set(SERVING_CREDENTIAL_TYPES.NAME, v.name())
            .execute();
    }
    public void addCredential(Credential v) {
        db.insertInto(SERVING_CREDENTIALS)
            .set(SERVING_CREDENTIALS.ID, v.id())
            .set(SERVING_CREDENTIALS.VOLUNTEER_ID, v.volunteerId())
            .set(SERVING_CREDENTIALS.TYPE_ID, v.typeId())
            .set(SERVING_CREDENTIALS.VALID_FROM, ServingRecords.time(v.validFrom()))
            .set(SERVING_CREDENTIALS.VALID_UNTIL, ServingRecords.time(v.validUntil()))
            .set(SERVING_CREDENTIALS.EVIDENCE, v.evidence())
            .set(SERVING_CREDENTIALS.VERIFIED_BY, v.verifiedBy())
            .set(SERVING_CREDENTIALS.VERIFIED_AT, ServingRecords.time(v.verifiedAt()))
            .set(SERVING_CREDENTIALS.REVOKED_AT, ServingRecords.time(v.revokedAt()))
            .execute();
    }
    public void availability(AvailabilityRule v) {
        db.insertInto(SERVING_AVAILABILITY_RULES)
            .set(SERVING_AVAILABILITY_RULES.ID, v.id())
            .set(SERVING_AVAILABILITY_RULES.VOLUNTEER_ID, v.volunteerId())
            .set(SERVING_AVAILABILITY_RULES.REVISION, v.revision())
            .set(SERVING_AVAILABILITY_RULES.DAY_OF_WEEK, v.dayOfWeek())
            .set(SERVING_AVAILABILITY_RULES.STARTS_AT, v.startsAt())
            .set(SERVING_AVAILABILITY_RULES.ENDS_AT, v.endsAt())
            .set(SERVING_AVAILABILITY_RULES.TIME_ZONE, v.timeZone())
            .set(SERVING_AVAILABILITY_RULES.VALID_FROM, v.validFrom())
            .set(SERVING_AVAILABILITY_RULES.VALID_UNTIL, v.validUntil())
            .execute();
    }
    public void exception(AvailabilityException v) {
        db.insertInto(SERVING_AVAILABILITY_EXCEPTIONS)
            .set(SERVING_AVAILABILITY_EXCEPTIONS.ID, v.id())
            .set(SERVING_AVAILABILITY_EXCEPTIONS.VOLUNTEER_ID, v.volunteerId())
            .set(SERVING_AVAILABILITY_EXCEPTIONS.REVISION, v.revision())
            .set(SERVING_AVAILABILITY_EXCEPTIONS.STARTS_AT, ServingRecords.time(v.startsAt()))
            .set(SERVING_AVAILABILITY_EXCEPTIONS.ENDS_AT, ServingRecords.time(v.endsAt()))
            .set(SERVING_AVAILABILITY_EXCEPTIONS.AVAILABLE, v.available())
            .execute();
    }
}
