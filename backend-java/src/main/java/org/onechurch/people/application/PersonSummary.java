package org.onechurch.people.application;

import org.onechurch.people.domain.AgeCategory;
import org.onechurch.people.domain.MembershipType;
import org.onechurch.people.domain.Person;
import org.onechurch.people.domain.PersonDetails;

import java.time.Instant;
import java.time.LocalDate;
import java.util.UUID;

public record PersonSummary(UUID id, PersonDetails details, MembershipType membershipType, AgeCategory ageCategory,
    LocalDate ageAsOf, Instant archivedAt, long version) {

    public static PersonSummary of(Person person, LocalDate asOf) {
        return new PersonSummary(person.id(), person.details(), person.membershipType(), person.ageCategory(asOf), asOf,
            person.archivedAt(), person.version());
    }
}
