package org.onechurch.giving.domain.repository;
import org.onechurch.giving.domain.Fund;
import org.onechurch.giving.domain.Gift;

import java.util.Optional;
import java.util.UUID;
public interface GivingRepository {
    Optional<Fund> lockFund(UUID id);
    Optional<Gift> lockGift(UUID id);
    Optional<UUID> fundRequest(UUID id, String hash);
    Optional<UUID> giftRequest(UUID id, String hash);
    Optional<UUID> reversalRequest(UUID id, String hash);
    boolean insertFund(Fund fund, String hash);
    void saveFund(Fund fund);
    void insertGift(Gift gift, String hash);
    boolean reversed(UUID giftId);
    long refunded(UUID giftId);
    void insertReversal(Gift.Reversal reversal, String hash);
    void audit(UUID target, String targetType, String action, String reason, UUID actor, java.time.Instant at);
}
