package org.onechurch.payments.application.command.removeyocowebhook;
import java.util.UUID;
public record RemoveYocoWebhookCommand(UUID actorId, UUID requestId, String mode, long expectedVersion, String reason,
    String subscriptionId) {}
