package org.onechurch.giving.application.command.createpledge;
import java.time.LocalDate;
import java.util.UUID;
public record CreatePledgeCommand(UUID actorId, UUID requestId, UUID donorId, UUID fundId, long amountMinor,
    String currency, LocalDate startsOn, LocalDate endsOn) {}
