package org.onechurch.giving.application.command.createfund;
import java.util.UUID;
public record CreateFundCommand(UUID actorId, UUID requestId, String name, String description) {}
