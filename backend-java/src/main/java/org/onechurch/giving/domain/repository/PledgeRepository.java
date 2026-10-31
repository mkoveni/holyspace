package org.onechurch.giving.domain.repository;
import org.onechurch.giving.domain.Pledge;
import org.onechurch.giving.domain.PledgeAssignment;

import java.util.Optional;
import java.util.UUID;
public interface PledgeRepository {
    Optional<UUID> pledgeRequest(UUID id, String hash);
    Optional<UUID> assignmentRequest(UUID id, String hash);
    void insert(Pledge pledge, String hash);
    Optional<Pledge> lock(UUID id);
    void save(Pledge pledge);
    Optional<PledgeAssignment> assignment(UUID id);
    void insert(PledgeAssignment assignment, String hash);
    void save(PledgeAssignment assignment);
    long credited(UUID pledgeId);
    long assigned(UUID giftId, UUID fundId);
}
