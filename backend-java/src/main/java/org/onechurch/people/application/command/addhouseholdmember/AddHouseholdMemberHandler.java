package org.onechurch.people.application.command.addhouseholdmember;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PeopleAuditWriter;
import org.onechurch.people.domain.Household;
import org.onechurch.people.domain.HouseholdMembership;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.Person;
import org.onechurch.people.domain.repository.HouseholdRepository;
import org.onechurch.people.domain.repository.PersonRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.UUID;

public final class AddHouseholdMemberHandler {

    private final PermissionChecker authorization;
    private final PeopleAuditWriter audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final HouseholdRepository households;
    private final PersonRepository people;

    public AddHouseholdMemberHandler(PermissionChecker authorization, PeopleAuditWriter audit, UnitOfWork transactions,
        Clock clock, HouseholdRepository households, PersonRepository people) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.households = households;
        this.people = people;
    }

    public UUID handle(AddHouseholdMemberCommand command) {
        authorization.requirePermission(command.actorId(), "people.manage");
        return transactions.execute(() -> {
            Household household =
                households.findForUpdate(command.householdId()).orElseThrow(() -> PeopleException.missing("Household"));
            Person person =
                people.findForUpdate(command.personId()).orElseThrow(() -> PeopleException.missing("Person"));
            person.requireAvailable();
            Instant now = clock.instant();
            households.update(household.touch(command.expectedVersion(), now));
            HouseholdMembership member =
                new HouseholdMembership(UUID.randomUUID(), household.id(), person.id(), command.primary(), now, null);
            households.add(member);
            audit.audit(command.actorId(), "household.member.added", household.id(), now);
            return member.id();
        });
    }
}
