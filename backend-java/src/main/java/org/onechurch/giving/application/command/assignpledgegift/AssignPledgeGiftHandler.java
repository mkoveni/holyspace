package org.onechurch.giving.application.command.assignpledgegift;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.GivingHash;
import org.onechurch.giving.domain.Gift;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.GivingRules;
import org.onechurch.giving.domain.Pledge;
import org.onechurch.giving.domain.PledgeAssignment;
import org.onechurch.giving.domain.repository.GivingRepository;
import org.onechurch.giving.domain.repository.PledgeRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.Optional;
import java.util.UUID;
public final class AssignPledgeGiftHandler {
    private final GivingAccess access;
    private final GivingRepository giving;
    private final UnitOfWork tx;
    private final Clock clock;
    private final PledgeRepository pledges;
    public AssignPledgeGiftHandler(GivingAccess access, GivingRepository giving, UnitOfWork tx, Clock clock,
        PledgeRepository pledges) {
        this.access = access;
        this.giving = giving;
        this.tx = tx;
        this.clock = clock;
        this.pledges = pledges;
    }
    public UUID handle(AssignPledgeGiftCommand c) {
        access.manage(c.actorId());
        String hash = GivingHash.of("pledge-assignment", c.actorId(), c.pledgeId(), c.giftId(), c.expectedVersion(),
            c.amountMinor());
        return tx.execute(() -> {
            Gift gift = giving.lockGift(c.giftId()).orElseThrow(GivingException::missing);
            Pledge pledge = pledges.lock(c.pledgeId()).orElseThrow(GivingException::missing);
            access.manage(c.actorId());
            Optional<UUID> replay = pledges.assignmentRequest(c.requestId(), hash);
            if (replay.isPresent()) {
                return replay.orElseThrow();
            }
            GivingRules.version(pledge.version(), c.expectedVersion());
            if (giving.reversed(gift.id())) {
                throw GivingException.conflict("A reversed gift cannot fulfil a pledge.");
            }
            long allocated = gift.allocations()
                .stream()
                .filter(a -> a.fundId().equals(pledge.fundId()))
                .mapToLong(Gift.Allocation::amountMinor)
                .sum();
            pledge.validateAssignment(gift, c.amountMinor(), pledges.credited(pledge.id()),
                allocated - giving.refunded(gift.id()) - pledges.assigned(gift.id(), pledge.fundId()));
            PledgeAssignment assignment = new PledgeAssignment(c.requestId(), pledge.id(), gift.id(), pledge.fundId(),
                pledge.donorId(), pledge.target().currency(), c.amountMinor(), "ACTIVE", 0, null, null, null,
                c.actorId(), clock.instant());
            pledges.insert(assignment, hash);
            pledges.save(pledge.revised());
            giving.audit(pledge.id(), "PLEDGE", "GIFT_ASSIGNED", "", c.actorId(), clock.instant());
            return assignment.id();
        });
    }
}
