package org.onechurch.people.domain.repository;

import org.onechurch.people.domain.ContactMethod;
import org.onechurch.people.domain.Person;
import org.onechurch.people.domain.PersonAddress;

import java.time.Instant;
import java.util.Optional;
import java.util.UUID;

public interface PersonRepository {
    Optional<Person> findForUpdate(UUID id);
    void insert(Person person);
    void update(Person person);
    void addContact(ContactMethod contact);
    void endContact(UUID personId, UUID contactId, Instant now);
    void addAddress(PersonAddress address);
    void endAddress(UUID personId, UUID addressId, Instant now);
}
