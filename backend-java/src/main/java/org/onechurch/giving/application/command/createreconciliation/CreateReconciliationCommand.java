package org.onechurch.giving.application.command.createreconciliation;
import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
public record CreateReconciliationCommand(UUID actorId, UUID requestId, String reference, long expectedMinor,
    String currency, LocalDate reconciledOn, String reason, List<UUID> giftIds) {}
