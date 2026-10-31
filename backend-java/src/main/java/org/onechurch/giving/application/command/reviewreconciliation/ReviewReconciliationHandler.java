package org.onechurch.giving.application.command.reviewreconciliation;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.GivingHash;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.GivingRules;
import org.onechurch.giving.domain.Reconciliation;
import org.onechurch.giving.domain.repository.GivingRepository;
import org.onechurch.giving.domain.repository.ReconciliationRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.util.Comparator;
import java.util.Optional;
import java.util.UUID;
public final class ReviewReconciliationHandler {
    private final GivingAccess access;
    private final GivingRepository giving;
    private final UnitOfWork tx;
    private final Clock clock;
    private final ReconciliationRepository batches;
    public ReviewReconciliationHandler(GivingAccess access, GivingRepository giving, UnitOfWork tx, Clock clock,
        ReconciliationRepository batches) {
        this.access = access;
        this.giving = giving;
        this.tx = tx;
        this.clock = clock;
        this.batches = batches;
    }
    public UUID handle(ReviewReconciliationCommand c) {
        access.reconcile(c.actorId());
        String note = GivingRules.text(c.note(), 1000, "Review evidence");
        String hash = GivingHash.of("batch-review", c.actorId(), c.batchId(), c.expectedVersion(), c.outcome(), note);
        return tx.execute(() -> {
            Reconciliation batch = batches.find(c.batchId()).orElseThrow(GivingException::missing);
            for (UUID gift : batch.giftIds().stream().sorted(Comparator.comparing(UUID::toString)).toList()) {
                giving.lockGift(gift).orElseThrow(GivingException::missing);
            }
            batches.lock(batch.id());
            access.reconcile(c.actorId());
            Optional<UUID> replay = batches.reviewRequest(c.requestId(), hash);
            if (replay.isPresent()) {
                return replay.orElseThrow();
            }
            long version = batches.version(batch.id());
            GivingRules.version(version, c.expectedVersion());
            Reconciliation.Review review = new Reconciliation.Review(c.requestId(), batch.id(), version + 1,
                c.outcome(), note, batches.reversedMinor(batch.id()), c.actorId(), clock.instant());
            batches.insert(review, hash);
            giving.audit(batch.id(), "BATCH", "BATCH_REVIEWED", note, c.actorId(), clock.instant());
            return review.id();
        });
    }
}
