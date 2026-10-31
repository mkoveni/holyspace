package org.onechurch.people.application.command.savemycontact;
import org.onechurch.identityaccess.application.contract.MemberIdentity;
import org.onechurch.identityaccess.application.contract.MemberIdentityGuard;
import org.onechurch.people.application.contract.PeopleAuditWriter;
import org.onechurch.people.application.contract.PersonReader;
import org.onechurch.people.domain.ContactMethod;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.Person;
import org.onechurch.people.domain.repository.PersonRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.UUID;
public final class SaveMyContactHandler {
    private final MemberIdentity identity;
    private final MemberIdentityGuard guard;
    private final PersonRepository people;
    private final PersonReader reader;
    private final PeopleAuditWriter audit;
    private final UnitOfWork tx;
    private final Clock clock;
    public SaveMyContactHandler(MemberIdentity identity, MemberIdentityGuard guard, PersonRepository people,
        PersonReader reader, PeopleAuditWriter audit, UnitOfWork tx, Clock clock) {
        this.identity = identity;
        this.guard = guard;
        this.people = people;
        this.reader = reader;
        this.audit = audit;
        this.tx = tx;
        this.clock = clock;
    }
    public UUID handle(SaveMyContactCommand c) {
        identity.requirePerson(c.actorId());
        return tx.execute(() -> {
            Person person = people.findForUpdate(guard.lockPerson(c.actorId()))
                .orElseThrow(() -> PeopleException.missing("Person"));
            identity.requirePerson(c.actorId());
            person.requireAvailable();
            ContactMethod added =
                new ContactMethod(UUID.randomUUID(), person.id(), c.kind(), c.value(), true, clock.instant(), null);
            if (c.contactId() != null) {
                reader.contacts(person.id())
                    .stream()
                    .filter(contact -> contact.id().equals(c.contactId()) && contact.endedAt() == null
                        && contact.kind() == c.kind())
                    .findFirst()
                    .orElseThrow(() -> PeopleException.missing("Contact"));
                people.endContact(person.id(), c.contactId(), clock.instant());
            }
            people.update(person.touch(c.expectedVersion(), clock.instant()));
            people.addContact(added);
            audit.audit(c.actorId(), "person.contact.self_saved", person.id(), clock.instant());
            return added.id();
        });
    }
}
