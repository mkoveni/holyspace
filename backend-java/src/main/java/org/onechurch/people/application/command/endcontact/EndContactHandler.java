package org.onechurch.people.application.command.endcontact;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PeopleAuditWriter;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.Person;
import org.onechurch.people.domain.repository.PersonRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;

public final class EndContactHandler {

    private final PermissionChecker authorization;
    private final PeopleAuditWriter audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final PersonRepository people;

    public EndContactHandler(PermissionChecker authorization, PeopleAuditWriter audit, UnitOfWork transactions,
        Clock clock, PersonRepository people) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.people = people;
    }

    public void handle(EndContactCommand command) {
        authorization.requirePermission(command.actorId(), "people.manage");
        transactions.execute(() -> {
            Person person =
                people.findForUpdate(command.personId()).orElseThrow(() -> PeopleException.missing("Person"));
            Instant now = clock.instant();
            people.update(person.touch(command.expectedVersion(), now));
            people.endContact(person.id(), command.contactId(), now);
            audit.audit(command.actorId(), "person.contact.ended", person.id(), now);
            return null;
        });
    }
}
