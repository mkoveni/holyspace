package org.onechurch.people.infrastructure.persistence;

import org.onechurch.people.domain.ContactMethod;
import org.onechurch.people.domain.Gender;
import org.onechurch.people.domain.Household;
import org.onechurch.people.domain.HouseholdMembership;
import org.onechurch.people.domain.LifeStatus;
import org.onechurch.people.domain.MembershipType;
import org.onechurch.people.domain.Person;
import org.onechurch.people.domain.PersonAddress;
import org.onechurch.people.domain.PersonDetails;
import org.onechurch.people.domain.PersonRelationship;
import org.onechurch.people.domain.RelationshipType;
import org.onechurch.shared.infrastructure.jooq.tables.records.PeopleAddressesRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.PeopleContactMethodsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.PeopleHouseholdMembershipsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.PeopleHouseholdsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.PeoplePersonsRecord;
import org.onechurch.shared.infrastructure.jooq.tables.records.PeopleRelationshipsRecord;

import java.time.Instant;
import java.time.OffsetDateTime;
import java.time.ZoneOffset;

final class PeopleRecordMapper {

    private PeopleRecordMapper() {
    }

    static OffsetDateTime time(Instant at) {
        return at == null ? null : at.atOffset(ZoneOffset.UTC);
    }

    static Instant instant(OffsetDateTime at) {
        return at == null ? null : at.toInstant();
    }

    static Person person(PeoplePersonsRecord row) {
        return new Person(row.getId(),
            new PersonDetails(row.getTitleCode(), row.getFirstName(), row.getLastName(), row.getDateOfBirth(),
                Gender.valueOf(row.getGender()), LifeStatus.valueOf(row.getLifeStatus()), row.getDateOfDeath()),
            MembershipType.valueOf(row.getMembershipType()), instant(row.getArchivedAt()), row.getVersion(),
            instant(row.getCreatedAt()), instant(row.getUpdatedAt()));
    }

    static Household household(PeopleHouseholdsRecord row) {
        return new Household(row.getId(), row.getName(), row.getPrimaryContactMembershipId(), row.getVersion(),
            instant(row.getCreatedAt()), instant(row.getUpdatedAt()));
    }

    static HouseholdMembership membership(PeopleHouseholdMembershipsRecord row) {
        return new HouseholdMembership(row.getId(), row.getHouseholdId(), row.getPersonId(), row.getIsPrimary(),
            instant(row.getValidFrom()), instant(row.getValidTo()));
    }

    static PersonRelationship relationship(PeopleRelationshipsRecord row) {
        return new PersonRelationship(row.getId(), row.getSourceId(), row.getTargetId(),
            RelationshipType.valueOf(row.getType()), instant(row.getValidFrom()), instant(row.getValidTo()),
            row.getVersion());
    }

    static ContactMethod contact(PeopleContactMethodsRecord row) {
        return new ContactMethod(row.getId(), row.getPersonId(), ContactMethod.Kind.valueOf(row.getKind()),
            row.getValue(), row.getIsPrimary(), instant(row.getCreatedAt()), instant(row.getEndedAt()));
    }

    static PersonAddress address(PeopleAddressesRecord row) {
        return new PersonAddress(row.getId(), row.getPersonId(), row.getLine1(), row.getLine2(), row.getCity(),
            row.getRegion(), row.getPostalCode(), row.getCountryCode(), instant(row.getCreatedAt()),
            instant(row.getEndedAt()));
    }
}
