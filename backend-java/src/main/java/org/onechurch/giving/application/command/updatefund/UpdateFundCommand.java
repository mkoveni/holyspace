package org.onechurch.giving.application.command.updatefund;
import java.util.UUID;
public record UpdateFundCommand(UUID actorId, UUID fundId, long expectedVersion, String name, String description,
    boolean active, String reason) {}
