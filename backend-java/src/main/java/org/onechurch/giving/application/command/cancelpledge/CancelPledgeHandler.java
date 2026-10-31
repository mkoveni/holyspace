package org.onechurch.giving.application.command.cancelpledge;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.Pledge;
import org.onechurch.giving.domain.repository.GivingRepository;
import org.onechurch.giving.domain.repository.PledgeRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
public final class CancelPledgeHandler {
    private final GivingAccess access;
    private final GivingRepository giving;
    private final UnitOfWork tx;
    private final Clock clock;
    private final PledgeRepository pledges;
    public CancelPledgeHandler(GivingAccess access, GivingRepository giving, UnitOfWork tx, Clock clock,
        PledgeRepository pledges) {
        this.access = access;
        this.giving = giving;
        this.tx = tx;
        this.clock = clock;
        this.pledges = pledges;
    }
    public void handle(CancelPledgeCommand c) {
        access.manage(c.actorId());
        tx.execute(() -> {
            Pledge pledge = pledges.lock(c.pledgeId()).orElseThrow(GivingException::missing);
            access.manage(c.actorId());
            Pledge cancelled = pledge.cancel(c.expectedVersion(), c.reason());
            pledges.save(cancelled);
            giving.audit(pledge.id(), "PLEDGE", "PLEDGE_CANCELLED", cancelled.cancellationReason(), c.actorId(),
                clock.instant());
            return null;
        });
    }
}
