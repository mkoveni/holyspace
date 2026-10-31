package org.onechurch.childrenministry.messaging.application.command.retryemaildelivery;
import java.util.UUID;
public record RetryEmailDeliveryCommand(UUID actorId, UUID deliveryId, long expectedVersion, String reason) {}
