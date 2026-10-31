package org.onechurch.people.application.command.addcontact;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PeopleAuditWriter;
import org.onechurch.people.domain.ContactMethod;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.Person;
import org.onechurch.people.domain.repository.PersonRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.UUID;

public final class AddContactHandler {

    private final PermissionChecker authorization;
    private final PeopleAuditWriter audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final PersonRepository people;

    public AddContactHandler(PermissionChecker authorization, PeopleAuditWriter audit, UnitOfWork transactions,
        Clock clock, PersonRepository people) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.people = people;
    }

    public UUID handle(AddContactCommand command) {
        authorization.requirePermission(command.actorId(), "people.manage");
        return transactions.execute(() -> {
            Person person =
                people.findForUpdate(command.personId()).orElseThrow(() -> PeopleException.missing("Person"));
            Instant now = clock.instant();
            person.requireAvailable();
            people.update(person.touch(command.expectedVersion(), now));
            ContactMethod contact = new ContactMethod(UUID.randomUUID(), person.id(), command.kind(), command.value(),
                command.primary(), now, null);
            people.addContact(contact);
            audit.audit(command.actorId(), "person.contact.added", person.id(), now);
            return contact.id();
        });
    }
}
