package org.onechurch.people.application.command.setpersonarchived;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PeopleAuditWriter;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.Person;
import org.onechurch.people.domain.repository.PersonRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;

public final class SetPersonArchivedHandler {

    private final PermissionChecker authorization;
    private final PeopleAuditWriter audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final PersonRepository people;

    public SetPersonArchivedHandler(PermissionChecker authorization, PeopleAuditWriter audit, UnitOfWork transactions,
        Clock clock, PersonRepository people) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.people = people;
    }

    public void handle(SetPersonArchivedCommand command) {
        authorization.requirePermission(command.actorId(), "people.manage");
        transactions.execute(() -> {
            Person person =
                people.findForUpdate(command.personId()).orElseThrow(() -> PeopleException.missing("Person"));
            Instant now = clock.instant();
            people.update(person.archive(command.archived(), command.expectedVersion(), now));
            audit.audit(command.actorId(), command.archived() ? "person.archived" : "person.restored", person.id(),
                now);
            return null;
        });
    }
}
