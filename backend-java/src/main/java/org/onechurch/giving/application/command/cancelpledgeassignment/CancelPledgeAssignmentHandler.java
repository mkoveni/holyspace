package org.onechurch.giving.application.command.cancelpledgeassignment;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.Pledge;
import org.onechurch.giving.domain.PledgeAssignment;
import org.onechurch.giving.domain.repository.GivingRepository;
import org.onechurch.giving.domain.repository.PledgeRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
public final class CancelPledgeAssignmentHandler {
    private final GivingAccess access;
    private final GivingRepository giving;
    private final UnitOfWork tx;
    private final Clock clock;
    private final PledgeRepository pledges;
    public CancelPledgeAssignmentHandler(GivingAccess access, GivingRepository giving, UnitOfWork tx, Clock clock,
        PledgeRepository pledges) {
        this.access = access;
        this.giving = giving;
        this.tx = tx;
        this.clock = clock;
        this.pledges = pledges;
    }
    public void handle(CancelPledgeAssignmentCommand c) {
        access.manage(c.actorId());
        tx.execute(() -> {
            PledgeAssignment reference = pledges.assignment(c.assignmentId()).orElseThrow(GivingException::missing);
            giving.lockGift(reference.giftId()).orElseThrow(GivingException::missing);
            Pledge pledge = pledges.lock(reference.pledgeId()).orElseThrow(GivingException::missing);
            access.manage(c.actorId());
            PledgeAssignment current = pledges.assignment(c.assignmentId()).orElseThrow(GivingException::missing),
                cancelled = current.cancel(c.expectedVersion(), c.reason(), c.actorId(), clock.instant());
            pledges.save(cancelled);
            pledges.save(pledge.revised());
            giving.audit(pledge.id(), "PLEDGE", "ASSIGNMENT_CANCELLED", cancelled.cancellationReason(), c.actorId(),
                clock.instant());
            return null;
        });
    }
}
