package org.onechurch.giving.domain.repository;
import org.onechurch.giving.domain.Acknowledgement;

import java.util.Optional;
import java.util.UUID;
public interface AcknowledgementRepository {
    Optional<UUID> request(UUID id, String hash);
    Optional<UUID> forGift(UUID giftId);
    void insert(Acknowledgement acknowledgement, String hash);
}
