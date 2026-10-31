package org.onechurch.payments.application.command.closewebhookoperation;
import java.util.UUID;
public record CloseWebhookOperationCommand(UUID actorId, UUID requestId, long expectedVersion, String reason) {}
