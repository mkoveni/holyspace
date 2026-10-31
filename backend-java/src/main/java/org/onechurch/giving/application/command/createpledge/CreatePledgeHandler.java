package org.onechurch.giving.application.command.createpledge;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.GivingHash;
import org.onechurch.giving.domain.Fund;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.Money;
import org.onechurch.giving.domain.Pledge;
import org.onechurch.giving.domain.repository.GivingRepository;
import org.onechurch.giving.domain.repository.PledgeRepository;
import org.onechurch.people.application.contract.PersonDirectory;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.Optional;
import java.util.UUID;
public final class CreatePledgeHandler {
    private final GivingAccess access;
    private final GivingRepository giving;
    private final UnitOfWork tx;
    private final Clock clock;
    private final PledgeRepository pledges;
    private final PersonDirectory people;
    public CreatePledgeHandler(GivingAccess access, GivingRepository giving, UnitOfWork tx, Clock clock,
        PledgeRepository pledges, PersonDirectory people) {
        this.access = access;
        this.giving = giving;
        this.tx = tx;
        this.clock = clock;
        this.pledges = pledges;
        this.people = people;
    }
    public UUID handle(CreatePledgeCommand c) {
        access.manage(c.actorId());
        Pledge pledge = new Pledge(c.requestId(), c.donorId(), c.fundId(), new Money(c.amountMinor(), c.currency()),
            c.startsOn(), c.endsOn(), "OPEN", 0, null, c.actorId(), clock.instant());
        String hash = GivingHash.of("pledge", c.actorId(), pledge.donorId(), pledge.fundId(), pledge.target(),
            pledge.startsOn(), pledge.endsOn());
        return tx.execute(() -> {
            Optional<UUID> replay = pledges.pledgeRequest(c.requestId(), hash);
            if (replay.isPresent()) {
                return replay.orElseThrow();
            }
            people.lock(pledge.donorId())
                .filter(PersonDirectory.Entry::available)
                .orElseThrow(() -> GivingException.invalid("Choose an available donor."));
            giving.lockFund(pledge.fundId())
                .filter(Fund::active)
                .orElseThrow(() -> GivingException.conflict("Choose an active fund."));
            access.manage(c.actorId());
            Optional<UUID> retry = pledges.pledgeRequest(c.requestId(), hash);
            if (retry.isPresent()) {
                return retry.orElseThrow();
            }
            pledges.insert(pledge, hash);
            giving.audit(pledge.id(), "PLEDGE", "PLEDGE_CREATED", "", c.actorId(), clock.instant());
            return pledge.id();
        });
    }
}
