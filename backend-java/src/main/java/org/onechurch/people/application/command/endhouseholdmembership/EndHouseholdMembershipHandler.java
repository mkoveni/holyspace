package org.onechurch.people.application.command.endhouseholdmembership;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.people.application.contract.PeopleAuditWriter;
import org.onechurch.people.domain.Household;
import org.onechurch.people.domain.HouseholdMembership;
import org.onechurch.people.domain.PeopleException;
import org.onechurch.people.domain.repository.HouseholdRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.Instant;

public final class EndHouseholdMembershipHandler {

    private final PermissionChecker authorization;
    private final PeopleAuditWriter audit;
    private final UnitOfWork transactions;
    private final Clock clock;
    private final HouseholdRepository households;

    public EndHouseholdMembershipHandler(PermissionChecker authorization, PeopleAuditWriter audit,
        UnitOfWork transactions, Clock clock, HouseholdRepository households) {
        this.authorization = authorization;
        this.audit = audit;
        this.transactions = transactions;
        this.clock = clock;
        this.households = households;
    }

    public void handle(EndHouseholdMembershipCommand command) {
        authorization.requirePermission(command.actorId(), "people.manage");
        transactions.execute(() -> {
            Household household =
                households.findForUpdate(command.householdId()).orElseThrow(() -> PeopleException.missing("Household"));
            HouseholdMembership member = households.member(household.id(), command.membershipId())
                .orElseThrow(() -> PeopleException.missing("Membership"));
            Instant now = clock.instant();
            Household updated = member.id().equals(household.primaryContactMembershipId())
                ? household.contact(null, command.expectedVersion(), now)
                : household.touch(command.expectedVersion(), now);
            households.update(updated);
            households.end(member.end(now));
            audit.audit(command.actorId(), "household.member.ended", household.id(), now);
            return null;
        });
    }
}
