package org.onechurch.people.infrastructure;
import org.onechurch.people.application.contract.PersonEmailDirectory;
import org.onechurch.people.application.contract.PersonReader;
import org.onechurch.people.domain.ContactMethod;
import org.springframework.stereotype.Component;

import java.util.List;
import java.util.UUID;
@Component
public final class PublishedPersonEmailDirectory implements PersonEmailDirectory {
    private final PersonReader reader;
    public PublishedPersonEmailDirectory(PersonReader reader) {
        this.reader = reader;
    }
    public List<Email> active(UUID personId) {
        return reader.contacts(personId)
            .stream()
            .filter(c -> c.kind() == ContactMethod.Kind.EMAIL && c.endedAt() == null)
            .map(c -> new Email(c.id(), c.value()))
            .toList();
    }
}
