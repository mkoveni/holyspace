package org.onechurch.people.infrastructure.persistence;
import static org.onechurch.people.infrastructure.persistence.PeopleRecordMapper.time;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_ADDRESSES;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_CONTACT_METHODS;
import static org.onechurch.shared.infrastructure.jooq.Tables.PEOPLE_PERSONS;

import org.jooq.Condition;
import org.jooq.DSLContext;
import org.jooq.impl.DSL;
import org.onechurch.people.application.contract.PersonReader;
import org.onechurch.people.domain.AgeCategory;
import org.onechurch.people.domain.AgePolicy;
import org.onechurch.people.domain.ContactMethod;
import org.onechurch.people.domain.MembershipType;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.Person;
import org.onechurch.people.domain.PersonAddress;
import org.onechurch.people.domain.repository.PersonRepository;
import org.onechurch.shared.infrastructure.jooq.tables.records.PeoplePersonsRecord;
import org.springframework.stereotype.Repository;

import java.time.Instant;
import java.time.LocalDate;
import java.util.Collection;
import java.util.List;
import java.util.Optional;
import java.util.UUID;

@Repository
public class JooqPersonStore implements PersonRepository, PersonReader {

    private final DSLContext db;

    public JooqPersonStore(DSLContext db) {
        this.db = db;
    }

    @Override
    public List<Person> findAll(Collection<UUID> ids) {
        return db.selectFrom(PEOPLE_PERSONS).where(PEOPLE_PERSONS.ID.in(ids)).fetch(PeopleRecordMapper::person);
    }
    public List<UUID> matchingIds(String search) {
        return db.select(PEOPLE_PERSONS.ID)
            .from(PEOPLE_PERSONS)
            .where(DSL.concat(PEOPLE_PERSONS.FIRST_NAME, DSL.inline(" "), PEOPLE_PERSONS.LAST_NAME)
                .containsIgnoreCase(search))
            .fetch(PEOPLE_PERSONS.ID);
    }
    public Optional<Person> find(UUID id) {
        return db.selectFrom(PEOPLE_PERSONS).where(PEOPLE_PERSONS.ID.eq(id)).fetchOptional(PeopleRecordMapper::person);
    }

    @Override
    public Optional<Person> findForUpdate(UUID id) {
        return db.selectFrom(PEOPLE_PERSONS)
            .where(PEOPLE_PERSONS.ID.eq(id))
            .forUpdate()
            .fetchOptional(PeopleRecordMapper::person);
    }

    @Override
    public List<Person> search(String search, boolean includeArchived, MembershipType membershipType,
        AgeCategory ageCategory, LocalDate asOf, int limit, int offset) {
        return db.selectFrom(PEOPLE_PERSONS)
            .where(PEOPLE_PERSONS.FIRST_NAME.containsIgnoreCase(search)
                .or(PEOPLE_PERSONS.LAST_NAME.containsIgnoreCase(search))
                .or(DSL.concat(PEOPLE_PERSONS.FIRST_NAME, DSL.inline(" "), PEOPLE_PERSONS.LAST_NAME)
                    .containsIgnoreCase(search)))
            .and(includeArchived ? DSL.noCondition() : PEOPLE_PERSONS.ARCHIVED_AT.isNull())
            .and(membershipType == null ? DSL.noCondition() : PEOPLE_PERSONS.MEMBERSHIP_TYPE.eq(membershipType.name()))
            .and(ageCondition(ageCategory, asOf))
            .orderBy(PEOPLE_PERSONS.LAST_NAME, PEOPLE_PERSONS.FIRST_NAME, PEOPLE_PERSONS.ID)
            .limit(limit)
            .offset(offset)
            .fetch(PeopleRecordMapper::person);
    }

    private Condition ageCondition(AgeCategory category, LocalDate asOf) {
        if (category == null) {
            return DSL.noCondition();
        }
        LocalDate cutoff = AgePolicy.adultBirthDateCutoff(asOf);
        return switch (category) {
            case CHILD -> PEOPLE_PERSONS.DATE_OF_BIRTH.gt(cutoff).and(PEOPLE_PERSONS.DATE_OF_BIRTH.le(asOf));
            case ADULT -> PEOPLE_PERSONS.DATE_OF_BIRTH.le(cutoff);
            case UNKNOWN -> PEOPLE_PERSONS.DATE_OF_BIRTH.isNull().or(PEOPLE_PERSONS.DATE_OF_BIRTH.gt(asOf));
        };
    }

    @Override
    public void insert(Person person) {
        db.insertInto(PEOPLE_PERSONS).set(record(person)).execute();
    }

    @Override
    public void update(Person person) {
        int changed = db.update(PEOPLE_PERSONS)
            .set(record(person))
            .where(PEOPLE_PERSONS.ID.eq(person.id()))
            .and(PEOPLE_PERSONS.VERSION.eq(person.version() - 1))
            .execute();
        if (changed != 1) {
            throw PeopleException.conflict("Person has changed. Reload and try again.");
        }
    }

    private PeoplePersonsRecord record(Person person) {
        PeoplePersonsRecord row = db.newRecord(PEOPLE_PERSONS);
        row.setId(person.id());
        row.setTitleCode(person.details().titleCode());
        row.setFirstName(person.details().firstName());
        row.setLastName(person.details().lastName());
        row.setDateOfBirth(person.details().dateOfBirth());
        row.setGender(person.details().gender().name());
        row.setMembershipType(person.membershipType().name());
        row.setLifeStatus(person.details().lifeStatus().name());
        row.setDateOfDeath(person.details().dateOfDeath());
        row.setArchivedAt(time(person.archivedAt()));
        row.setVersion(person.version());
        row.setCreatedAt(time(person.createdAt()));
        row.setUpdatedAt(time(person.updatedAt()));
        return row;
    }

    @Override
    public List<ContactMethod> contacts(UUID id) {
        return db.selectFrom(PEOPLE_CONTACT_METHODS)
            .where(PEOPLE_CONTACT_METHODS.PERSON_ID.eq(id))
            .orderBy(PEOPLE_CONTACT_METHODS.CREATED_AT, PEOPLE_CONTACT_METHODS.ID)
            .fetch(PeopleRecordMapper::contact);
    }

    @Override
    public void addContact(ContactMethod contact) {
        if (contact.primary()) {
            db.update(PEOPLE_CONTACT_METHODS)
                .set(PEOPLE_CONTACT_METHODS.IS_PRIMARY, false)
                .where(PEOPLE_CONTACT_METHODS.PERSON_ID.eq(contact.personId()))
                .and(PEOPLE_CONTACT_METHODS.KIND.eq(contact.kind().name()))
                .and(PEOPLE_CONTACT_METHODS.ENDED_AT.isNull())
                .execute();
        }
        db.insertInto(PEOPLE_CONTACT_METHODS)
            .set(PEOPLE_CONTACT_METHODS.ID, contact.id())
            .set(PEOPLE_CONTACT_METHODS.PERSON_ID, contact.personId())
            .set(PEOPLE_CONTACT_METHODS.KIND, contact.kind().name())
            .set(PEOPLE_CONTACT_METHODS.VALUE, contact.value())
            .set(PEOPLE_CONTACT_METHODS.IS_PRIMARY, contact.primary())
            .set(PEOPLE_CONTACT_METHODS.CREATED_AT, time(contact.createdAt()))
            .execute();
    }

    @Override
    public void endContact(UUID personId, UUID contactId, Instant now) {
        int changed = db.update(PEOPLE_CONTACT_METHODS)
            .set(PEOPLE_CONTACT_METHODS.ENDED_AT, time(now))
            .set(PEOPLE_CONTACT_METHODS.IS_PRIMARY, false)
            .where(PEOPLE_CONTACT_METHODS.ID.eq(contactId))
            .and(PEOPLE_CONTACT_METHODS.PERSON_ID.eq(personId))
            .and(PEOPLE_CONTACT_METHODS.ENDED_AT.isNull())
            .execute();
        if (changed != 1) {
            throw PeopleException.conflict("Contact method is missing or already ended.");
        }
    }

    @Override
    public List<PersonAddress> addresses(UUID id) {
        return db.selectFrom(PEOPLE_ADDRESSES)
            .where(PEOPLE_ADDRESSES.PERSON_ID.eq(id))
            .orderBy(PEOPLE_ADDRESSES.CREATED_AT, PEOPLE_ADDRESSES.ID)
            .fetch(PeopleRecordMapper::address);
    }

    @Override
    public void addAddress(PersonAddress address) {
        db.insertInto(PEOPLE_ADDRESSES)
            .set(PEOPLE_ADDRESSES.ID, address.id())
            .set(PEOPLE_ADDRESSES.PERSON_ID, address.personId())
            .set(PEOPLE_ADDRESSES.LINE1, address.line1())
            .set(PEOPLE_ADDRESSES.LINE2, address.line2())
            .set(PEOPLE_ADDRESSES.CITY, address.city())
            .set(PEOPLE_ADDRESSES.REGION, address.region())
            .set(PEOPLE_ADDRESSES.POSTAL_CODE, address.postalCode())
            .set(PEOPLE_ADDRESSES.COUNTRY_CODE, address.countryCode())
            .set(PEOPLE_ADDRESSES.CREATED_AT, time(address.createdAt()))
            .execute();
    }

    @Override
    public void endAddress(UUID personId, UUID addressId, Instant now) {
        int changed = db.update(PEOPLE_ADDRESSES)
            .set(PEOPLE_ADDRESSES.ENDED_AT, time(now))
            .where(PEOPLE_ADDRESSES.ID.eq(addressId))
            .and(PEOPLE_ADDRESSES.PERSON_ID.eq(personId))
            .and(PEOPLE_ADDRESSES.ENDED_AT.isNull())
            .execute();
        if (changed != 1) {
            throw PeopleException.conflict("Address is missing or already ended.");
        }
    }
}
