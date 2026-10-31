package org.onechurch.payments.application.command.registeryocowebhook;
import java.util.UUID;
public record RegisterYocoWebhookCommand(UUID actorId, UUID requestId, String mode, long expectedVersion,
    String reason) {}
