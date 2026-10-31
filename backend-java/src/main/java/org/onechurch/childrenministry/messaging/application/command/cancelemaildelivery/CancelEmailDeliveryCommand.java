package org.onechurch.childrenministry.messaging.application.command.cancelemaildelivery;
import java.util.UUID;
public record CancelEmailDeliveryCommand(UUID actorId, UUID deliveryId, long expectedVersion, String reason) {}
