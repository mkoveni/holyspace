package org.onechurch.giving.application.command.reversegift;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.GivingHash;
import org.onechurch.giving.domain.Gift;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.GivingRules;
import org.onechurch.giving.domain.repository.GivingRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.LocalDate;
import java.util.Optional;
import java.util.UUID;

public final class ReverseGiftHandler {
    private final GivingAccess access;
    private final GivingRepository repository;
    private final UnitOfWork tx;
    private final Clock clock;

    public ReverseGiftHandler(GivingAccess access, GivingRepository repository, UnitOfWork tx, Clock clock) {
        this.access = access;
        this.repository = repository;
        this.tx = tx;
        this.clock = clock;
    }
    public UUID handle(ReverseGiftCommand c) {
        access.reverse(c.actorId());
        GivingRules.date(c.reversedOn(), LocalDate.now(clock));
        String reason = GivingRules.text(c.reason(), 1000, "Reversal reason");
        String hash = GivingHash.of("reverse", c.actorId(), c.giftId(), c.reversedOn(), reason);
        return tx.execute(() -> {
            Gift gift = repository.lockGift(c.giftId()).orElseThrow(GivingException::missing);
            access.reverse(c.actorId());
            Optional<UUID> replay = repository.reversalRequest(c.requestId(), hash);
            if (replay.isPresent()) {
                return replay.orElseThrow();
            }
            if (gift.method().equals("ONLINE")) {
                throw GivingException.conflict("Use Online payments to record a provider-completed refund.");
            }
            if (repository.reversed(gift.id())) {
                throw GivingException.conflict("This gift already has a full reversal.");
            }
            Gift.Reversal reversal = gift.reverse(c.requestId(), c.reversedOn(), reason, c.actorId(), clock.instant());
            repository.insertReversal(reversal, hash);
            repository.audit(gift.id(), "GIFT", "GIFT_REVERSED", reason, c.actorId(), clock.instant());
            return reversal.id();
        });
    }
}
