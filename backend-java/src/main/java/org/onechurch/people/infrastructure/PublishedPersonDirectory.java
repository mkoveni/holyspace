package org.onechurch.people.infrastructure;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.people.application.contract.PersonReader;
import org.onechurch.people.domain.LifeStatus;
import org.onechurch.people.domain.Person;
import org.onechurch.people.domain.repository.PersonRepository;
import org.springframework.stereotype.Component;

import java.util.Collection;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
@Component
public class PublishedPersonDirectory implements PersonDirectory {
    private final PersonReader reader;
    private final PersonRepository repository;
    public PublishedPersonDirectory(PersonReader reader, PersonRepository repository) {
        this.reader = reader;
        this.repository = repository;
    }
    public List<Entry> getAll(Collection<UUID> ids) {
        return reader.findAll(ids).stream().map(PublishedPersonDirectory::entry).toList();
    }
    public List<UUID> matchingIds(String search) {
        return reader.matchingIds(search);
    }
    public Optional<Entry> get(UUID id) {
        return reader.find(id).map(PublishedPersonDirectory::entry);
    }
    public Optional<Entry> lock(UUID id) {
        return repository.findForUpdate(id).map(PublishedPersonDirectory::entry);
    }
    private static Entry entry(Person person) {
        return new Entry(person.id(), person.details().firstName() + " " + person.details().lastName(),
            person.details().dateOfBirth(),
            person.archivedAt() == null && person.details().lifeStatus() != LifeStatus.DECEASED);
    }
}
