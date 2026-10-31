package org.onechurch.giving.application.command.assignpledgegift;
import java.util.UUID;
public record AssignPledgeGiftCommand(UUID actorId, UUID requestId, UUID pledgeId, UUID giftId, long expectedVersion,
    long amountMinor) {}
