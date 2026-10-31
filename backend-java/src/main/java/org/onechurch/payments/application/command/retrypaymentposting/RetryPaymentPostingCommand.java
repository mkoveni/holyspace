package org.onechurch.payments.application.command.retrypaymentposting;
import java.util.UUID;
public record RetryPaymentPostingCommand(UUID actorId, UUID orderId, long expectedVersion) {}
