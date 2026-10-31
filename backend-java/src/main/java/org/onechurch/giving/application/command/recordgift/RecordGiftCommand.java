package org.onechurch.giving.application.command.recordgift;
import org.onechurch.giving.domain.Gift;

import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
public record RecordGiftCommand(UUID actorId, UUID requestId, UUID donorId, long amountMinor, String currency,
    LocalDate receivedOn, String method, String externalReference, List<Gift.Allocation> allocations) {}
