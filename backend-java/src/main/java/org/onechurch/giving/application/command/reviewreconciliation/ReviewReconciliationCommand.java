package org.onechurch.giving.application.command.reviewreconciliation;
import java.util.UUID;
public record ReviewReconciliationCommand(UUID actorId, UUID requestId, UUID batchId, long expectedVersion,
    String outcome, String note) {}
