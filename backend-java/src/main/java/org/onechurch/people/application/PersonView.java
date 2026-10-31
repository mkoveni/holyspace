package org.onechurch.people.application;
import org.onechurch.people.domain.AgeCategory;
import org.onechurch.people.domain.ContactMethod;
import org.onechurch.people.domain.MembershipType;
import org.onechurch.people.domain.Person;
import org.onechurch.people.domain.PersonAddress;
import org.onechurch.people.domain.PersonDetails;

import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.UUID;

public record PersonView(UUID id, PersonDetails details, MembershipType membershipType, AgeCategory ageCategory,
    LocalDate ageAsOf, Instant archivedAt, long version, List<ContactMethod> contacts, List<PersonAddress> addresses) {

    public static PersonView of(Person person, LocalDate asOf, List<ContactMethod> contacts,
        List<PersonAddress> addresses) {
        return new PersonView(person.id(), person.details(), person.membershipType(), person.ageCategory(asOf), asOf,
            person.archivedAt(), person.version(), List.copyOf(contacts), List.copyOf(addresses));
    }
}
