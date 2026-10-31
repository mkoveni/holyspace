package org.onechurch.giving.application.command.setonlinefund;
import java.util.UUID;
public record SetOnlineFundCommand(UUID actorId, UUID fundId, boolean enabled, long expectedVersion) {}
