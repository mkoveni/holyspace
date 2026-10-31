package org.onechurch.payments.application.command.checkproviderconnection;
import java.util.UUID;
public record CheckProviderConnectionCommand(UUID actorId, String provider, String mode, long expectedVersion) {}
