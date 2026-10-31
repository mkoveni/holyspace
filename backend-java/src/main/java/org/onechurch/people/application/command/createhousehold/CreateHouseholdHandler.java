package org.onechurch.people.application.command.createhousehold;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PeopleAuditWriter;
import org.onechurch.people.domain.Household;
import org.onechurch.people.domain.repository.HouseholdRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;
import java.util.UUID;

public final class CreateHouseholdHandler {

    private final PermissionChecker authorization;
    private final PeopleAuditWriter audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final HouseholdRepository households;

    public CreateHouseholdHandler(PermissionChecker authorization, PeopleAuditWriter audit, UnitOfWork transactions,
        Clock clock, HouseholdRepository households) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.households = households;
    }

    public UUID handle(CreateHouseholdCommand command) {
        authorization.requirePermission(command.actorId(), "people.manage");
        return transactions.execute(() -> {
            Instant now = clock.instant();
            Household household = Household.create(UUID.randomUUID(), command.name(), now);
            households.insert(household);
            audit.audit(command.actorId(), "household.created", household.id(), now);
            return household.id();
        });
    }
}
