package org.onechurch.people.application.command.sethouseholdcontact;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PeopleAuditWriter;
import org.onechurch.people.domain.Household;
import org.onechurch.people.domain.HouseholdMembership;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.repository.HouseholdRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;

public final class SetHouseholdContactHandler {

    private final PermissionChecker authorization;
    private final PeopleAuditWriter audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final HouseholdRepository households;

    public SetHouseholdContactHandler(PermissionChecker authorization, PeopleAuditWriter audit, UnitOfWork transactions,
        Clock clock, HouseholdRepository households) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.households = households;
    }

    public void handle(SetHouseholdContactCommand command) {
        authorization.requirePermission(command.actorId(), "people.manage");
        transactions.execute(() -> {
            Household household =
                households.findForUpdate(command.householdId()).orElseThrow(() -> PeopleException.missing("Household"));
            Instant now = clock.instant();
            HouseholdMembership member =
                command.membershipId() == null ? null : households.member(household.id(), command.membershipId())
                    .orElseThrow(() -> PeopleException.missing("Membership"));
            households.update(household.contact(member, command.expectedVersion(), now));
            audit.audit(command.actorId(), "household.contact.changed", household.id(), now);
            return null;
        });
    }
}
