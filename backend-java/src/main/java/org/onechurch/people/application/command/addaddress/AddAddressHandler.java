package org.onechurch.people.application.command.addaddress;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PeopleAuditWriter;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.Person;
import org.onechurch.people.domain.PersonAddress;
import org.onechurch.people.domain.repository.PersonRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.UUID;

public final class AddAddressHandler {

    private final PermissionChecker authorization;
    private final PeopleAuditWriter audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final PersonRepository people;

    public AddAddressHandler(PermissionChecker authorization, PeopleAuditWriter audit, UnitOfWork transactions,
        Clock clock, PersonRepository people) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.people = people;
    }

    public UUID handle(AddAddressCommand command) {
        authorization.requirePermission(command.actorId(), "people.manage");
        return transactions.execute(() -> {
            Person person =
                people.findForUpdate(command.personId()).orElseThrow(() -> PeopleException.missing("Person"));
            Instant now = clock.instant();
            person.requireAvailable();
            people.update(person.touch(command.expectedVersion(), now));
            PersonAddress address = new PersonAddress(UUID.randomUUID(), person.id(), command.line1(), command.line2(),
                command.city(), command.region(), command.postalCode(), command.countryCode(), now, null);
            people.addAddress(address);
            audit.audit(command.actorId(), "person.address.added", person.id(), now);
            return address.id();
        });
    }
}
