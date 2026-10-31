package org.onechurch.giving.application.command.createreconciliation;
import org.onechurch.giving.application.GivingAccess;
import org.onechurch.giving.application.GivingHash;
import org.onechurch.giving.domain.Gift;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.giving.domain.GivingRules;
import org.onechurch.giving.domain.Reconciliation;
import org.onechurch.giving.domain.repository.GivingRepository;
import org.onechurch.giving.domain.repository.ReconciliationRepository;
import org.onechurch.shared.application.UnitOfWork;

import java.time.Clock;
import java.time.LocalDate;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public final class CreateReconciliationHandler {
    private final GivingAccess access;
    private final GivingRepository giving;
    private final UnitOfWork tx;
    private final Clock clock;
    private final ReconciliationRepository batches;
    public CreateReconciliationHandler(GivingAccess access, GivingRepository giving, UnitOfWork tx, Clock clock,
        ReconciliationRepository batches) {
        this.access = access;
        this.giving = giving;
        this.tx = tx;
        this.clock = clock;
        this.batches = batches;
    }
    public UUID handle(CreateReconciliationCommand c) {
        access.reconcile(c.actorId());
        GivingRules.date(c.reconciledOn(), LocalDate.now(clock));
        String reference = GivingRules.text(c.reference(), 200, "Batch reference");
        if (c.giftIds() == null || c.giftIds().isEmpty() || c.giftIds().size() > 100
            || c.giftIds().stream().anyMatch(java.util.Objects::isNull)
            || c.giftIds().stream().distinct().count() != c.giftIds().size()) {
            throw GivingException.invalid("Select 1–100 distinct gifts.");
        }
        List<UUID> ids = c.giftIds().stream().sorted(Comparator.comparing(UUID::toString)).toList();
        String reason = c.reason() == null ? "" : c.reason().trim();
        String hash = GivingHash.of("batch", c.actorId(), reference, c.expectedMinor(), c.currency(), c.reconciledOn(),
            reason, ids);
        return tx.execute(() -> {
            Optional<UUID> replay = batches.request(c.requestId(), hash);
            if (replay.isPresent()) {
                return replay.orElseThrow();
            }
            List<Gift> gifts = new ArrayList<>();
            for (UUID id : ids) {
                gifts.add(giving.lockGift(id).orElseThrow(GivingException::missing));
            }
            access.reconcile(c.actorId());
            Optional<UUID> retry = batches.request(c.requestId(), hash);
            if (retry.isPresent()) {
                return retry.orElseThrow();
            }
            for (Gift gift : gifts) {
                if (!gift.money().currency().equals(c.currency()) || gift.receivedOn().isAfter(c.reconciledOn())
                    || giving.reversed(gift.id()) || batches.assigned(gift.id())) {
                    throw GivingException.conflict(
                        "Every selected gift must be unreconciled, unreversed, in the batch currency and received by the reconciliation date.");
                }
            }
            long actual = gifts.stream().mapToLong(g -> g.money().amountMinor()).sum();
            Reconciliation batch = new Reconciliation(c.requestId(), reference, c.expectedMinor(), actual, c.currency(),
                c.reconciledOn(), reason, c.actorId(), clock.instant(), ids);
            batches.insert(batch, hash);
            giving.audit(batch.id(), "BATCH", "BATCH_CREATED", batch.reason(), c.actorId(), clock.instant());
            return batch.id();
        });
    }
}
