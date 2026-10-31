package org.onechurch.people.application.command.registerperson;

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
import java.util.UUID;

public final class RegisterPersonHandler {

    private final PermissionChecker authorization;
    private final PeopleAuditWriter audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final PersonRepository people;
    private final TitleReader titles;
    private final ZoneId zone;

    public RegisterPersonHandler(PermissionChecker authorization, PeopleAuditWriter audit, UnitOfWork transactions,
        Clock clock, PersonRepository people, TitleReader titles, ZoneId zone) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.people = people;
        this.titles = titles;
        this.zone = zone;
    }

    public UUID handle(RegisterPersonCommand command) {
        authorization.requirePermission(command.actorId(), "people.manage");
        return transactions.execute(() -> {
            Instant now = clock.instant();
            if (command.details().titleCode() != null && !titles.exists(command.details().titleCode())) {
                throw PeopleException.invalid("Select an existing title.");
            }
            Person person = Person.register(UUID.randomUUID(), command.details(), command.membershipType(),
                LocalDate.now(clock.withZone(zone)), now);
            people.insert(person);
            audit.audit(command.actorId(), "person.registered", person.id(), now);
            return person.id();
        });
    }
}
