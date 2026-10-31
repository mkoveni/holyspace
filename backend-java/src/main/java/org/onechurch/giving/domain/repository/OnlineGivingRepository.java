package org.onechurch.giving.domain.repository;
import org.onechurch.giving.domain.PledgeAssignment;

import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
public interface OnlineGivingRepository {
    void source(UUID giftId, UUID chargeId, String reference);
    boolean sourceMatches(UUID giftId, String reference);
    boolean refundExists(UUID id, UUID gift, long amount, String reference, LocalDate date);
    void refund(UUID id, UUID gift, UUID fund, long amount, String reference, LocalDate date, UUID actor, Instant now);
    List<PledgeAssignment> activeAssignments(UUID gift);
}
