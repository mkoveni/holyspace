package org.onechurch.giving.application.command.reversegift;
import java.time.LocalDate;
import java.util.UUID;
public record ReverseGiftCommand(UUID actorId, UUID requestId, UUID giftId, LocalDate reversedOn, String reason) {}
