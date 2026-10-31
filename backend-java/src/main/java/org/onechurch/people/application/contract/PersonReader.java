package org.onechurch.people.application.contract;
import org.onechurch.people.domain.AgeCategory;
import org.onechurch.people.domain.ContactMethod;
import org.onechurch.people.domain.MembershipType;
import org.onechurch.people.domain.Person;
import org.onechurch.people.domain.PersonAddress;

import java.time.LocalDate;
import java.util.Collection;
import java.util.List;
import java.util.Optional;
import java.util.UUID;

public interface PersonReader {
    List<Person> findAll(Collection<UUID> ids);
    List<UUID> matchingIds(String search);
    Optional<Person> find(UUID id);
    List<Person> search(String search, boolean includeArchived, MembershipType membershipType, AgeCategory ageCategory,
        LocalDate asOf, int limit, int offset);
    List<ContactMethod> contacts(UUID id);
    List<PersonAddress> addresses(UUID id);
}
