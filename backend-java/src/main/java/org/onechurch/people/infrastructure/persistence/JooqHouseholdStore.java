package org.onechurch.people.infrastructure.persistence;

import static org.onechurch.people.infrastructure.persistence.PeopleRecordMapper.time;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_HOUSEHOLDS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_HOUSEHOLD_MEMBERSHIPS;

import org.jooq.DSLContext;
import org.onechurch.people.application.contract.HouseholdReader;
import org.onechurch.people.domain.Household;
import org.onechurch.people.domain.HouseholdMembership;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.repository.HouseholdRepository;
import org.onechurch.shared.infrastructure.jooq.tables.records.PeopleHouseholdsRecord;
import org.springframework.stereotype.Repository;

import java.util.List;
import java.util.Optional;
import java.util.UUID;

@Repository
public class JooqHouseholdStore implements HouseholdRepository, HouseholdReader {

    private final DSLContext db;

    public JooqHouseholdStore(DSLContext db) {
        this.db = db;
    }

    @Override
    public Optional<Household> find(UUID id) {
        return db.selectFrom(PEOPLE_HOUSEHOLDS)
            .where(PEOPLE_HOUSEHOLDS.ID.eq(id))
            .fetchOptional(PeopleRecordMapper::household);
    }

    @Override
    public Optional<Household> findForUpdate(UUID id) {
        return db.selectFrom(PEOPLE_HOUSEHOLDS)
            .where(PEOPLE_HOUSEHOLDS.ID.eq(id))
            .forUpdate()
            .fetchOptional(PeopleRecordMapper::household);
    }

    @Override
    public List<Household> search(String search, int limit, int offset) {
        return db.selectFrom(PEOPLE_HOUSEHOLDS)
            .where(PEOPLE_HOUSEHOLDS.NAME.containsIgnoreCase(search))
            .orderBy(PEOPLE_HOUSEHOLDS.NAME, PEOPLE_HOUSEHOLDS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(PeopleRecordMapper::household);
    }

    @Override
    public List<HouseholdMembership> members(UUID id) {
        return db.selectFrom(PEOPLE_HOUSEHOLD_MEMBERSHIPS)
            .where(PEOPLE_HOUSEHOLD_MEMBERSHIPS.HOUSEHOLD_ID.eq(id))
            .orderBy(PEOPLE_HOUSEHOLD_MEMBERSHIPS.VALID_FROM, PEOPLE_HOUSEHOLD_MEMBERSHIPS.ID)
            .fetch(PeopleRecordMapper::membership);
    }

    @Override
    public List<HouseholdMembership> memberships(UUID personId) {
        return db.selectFrom(PEOPLE_HOUSEHOLD_MEMBERSHIPS)
            .where(PEOPLE_HOUSEHOLD_MEMBERSHIPS.PERSON_ID.eq(personId))
            .orderBy(PEOPLE_HOUSEHOLD_MEMBERSHIPS.VALID_FROM, PEOPLE_HOUSEHOLD_MEMBERSHIPS.ID)
            .fetch(PeopleRecordMapper::membership);
    }

    @Override
    public Optional<HouseholdMembership> member(UUID householdId, UUID membershipId) {
        return db.selectFrom(PEOPLE_HOUSEHOLD_MEMBERSHIPS)
            .where(PEOPLE_HOUSEHOLD_MEMBERSHIPS.HOUSEHOLD_ID.eq(householdId))
            .and(PEOPLE_HOUSEHOLD_MEMBERSHIPS.ID.eq(membershipId))
            .fetchOptional(PeopleRecordMapper::membership);
    }

    @Override
    public void insert(Household household) {
        db.insertInto(PEOPLE_HOUSEHOLDS).set(record(household)).execute();
    }

    @Override
    public void update(Household household) {
        int changed = db.update(PEOPLE_HOUSEHOLDS)
            .set(record(household))
            .where(PEOPLE_HOUSEHOLDS.ID.eq(household.id()))
            .and(PEOPLE_HOUSEHOLDS.VERSION.eq(household.version() - 1))
            .execute();
        if (changed != 1) {
            throw PeopleException.conflict("Household has changed. Reload and try again.");
        }
    }

    private PeopleHouseholdsRecord record(Household household) {
        PeopleHouseholdsRecord row = db.newRecord(PEOPLE_HOUSEHOLDS);
        row.setId(household.id());
        row.setName(household.name());
        row.setPrimaryContactMembershipId(household.primaryContactMembershipId());
        row.setVersion(household.version());
        row.setCreatedAt(time(household.createdAt()));
        row.setUpdatedAt(time(household.updatedAt()));
        return row;
    }

    @Override
    public void add(HouseholdMembership member) {
        db.insertInto(PEOPLE_HOUSEHOLD_MEMBERSHIPS)
            .set(PEOPLE_HOUSEHOLD_MEMBERSHIPS.ID, member.id())
            .set(PEOPLE_HOUSEHOLD_MEMBERSHIPS.HOUSEHOLD_ID, member.householdId())
            .set(PEOPLE_HOUSEHOLD_MEMBERSHIPS.PERSON_ID, member.personId())
            .set(PEOPLE_HOUSEHOLD_MEMBERSHIPS.IS_PRIMARY, member.primary())
            .set(PEOPLE_HOUSEHOLD_MEMBERSHIPS.VALID_FROM, time(member.validFrom()))
            .execute();
    }

    @Override
    public void end(HouseholdMembership member) {
        int changed = db.update(PEOPLE_HOUSEHOLD_MEMBERSHIPS)
            .set(PEOPLE_HOUSEHOLD_MEMBERSHIPS.VALID_TO, time(member.validTo()))
            .where(PEOPLE_HOUSEHOLD_MEMBERSHIPS.ID.eq(member.id()))
            .and(PEOPLE_HOUSEHOLD_MEMBERSHIPS.VALID_TO.isNull())
            .execute();
        if (changed != 1) {
            throw PeopleException.conflict("Membership has already ended.");
        }
    }
}
