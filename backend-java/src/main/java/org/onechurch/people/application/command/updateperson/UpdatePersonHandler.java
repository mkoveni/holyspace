package org.onechurch.people.application.command.updateperson;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PeopleAuditWriter;
import org.onechurch.people.application.contract.TitleReader;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.Person;
import org.onechurch.people.domain.repository.PersonRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.time.LocalDate;
import java.time.ZoneId;

public final class UpdatePersonHandler {

    private final PermissionChecker authorization;
    private final PeopleAuditWriter audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final PersonRepository people;
    private final TitleReader titles;
    private final ZoneId zone;

    public UpdatePersonHandler(PermissionChecker authorization, PeopleAuditWriter audit, UnitOfWork transactions,
        Clock clock, PersonRepository people, TitleReader titles, ZoneId zone) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.people = people;
        this.titles = titles;
        this.zone = zone;
    }

    public void handle(UpdatePersonCommand command) {
        authorization.requirePermission(command.actorId(), "people.manage");
        transactions.execute(() -> {
            Person person =
                people.findForUpdate(command.personId()).orElseThrow(() -> PeopleException.missing("Person"));
            Instant now = clock.instant();
            if (command.details().titleCode() != null && !titles.exists(command.details().titleCode())) {
                throw PeopleException.invalid("Select an existing title.");
            }
            people.update(person.update(command.details(), command.membershipType(), command.expectedVersion(),
                LocalDate.now(clock.withZone(zone)), now));
            audit.audit(command.actorId(), "person.updated", person.id(), now);
            return null;
        });
    }
}
