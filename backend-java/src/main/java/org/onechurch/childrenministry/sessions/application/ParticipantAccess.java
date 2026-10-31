package org.onechurch.childrenministry.sessions.application;
import org.onechurch.childrenministry.shared.application.contract.ChildLocks;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.people.application.contract.PersonDirectory;

import java.util.Arrays;
import java.util.Comparator;
import java.util.UUID;
import java.util.stream.Stream;
public final class ParticipantAccess {
    private final PersonDirectory people;
    private final ChildLocks locks;
    public ParticipantAccess(PersonDirectory people, ChildLocks locks) {
        this.people = people;
        this.locks = locks;
    }
    public PersonDirectory.Entry person(UUID id) {
        return people.get(id).orElseThrow(() -> ChildrenException.missing("Person"));
    }
    public PersonDirectory.Entry available(UUID id) {
        PersonDirectory.Entry person = person(id);
        if (!person.available()) {
            throw ChildrenException.conflict("Restore the person or review their life status first.");
        }
        return person;
    }
    public void lockChild(UUID childId, UUID... others) {
        Stream.concat(Stream.of(childId), Arrays.stream(others))
            .distinct()
            .sorted(Comparator.comparing(UUID::toString))
            .forEach(id -> people.lock(id).orElseThrow(() -> ChildrenException.missing("Person")));
        locks.lock(childId);
    }
}
