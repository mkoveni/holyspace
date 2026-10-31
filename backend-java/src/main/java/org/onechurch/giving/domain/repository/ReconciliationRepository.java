package org.onechurch.giving.domain.repository;
import org.onechurch.giving.domain.Reconciliation;

import java.util.Optional;
import java.util.UUID;
public interface ReconciliationRepository {
    Optional<UUID> request(UUID id, String hash);
    Optional<UUID> reviewRequest(UUID id, String hash);
    void insert(Reconciliation batch, String hash);
    Optional<Reconciliation> find(UUID id);
    void lock(UUID id);
    long version(UUID batchId);
    long reversedMinor(UUID batchId);
    void insert(Reconciliation.Review review, String hash);
    boolean assigned(UUID giftId);
}
