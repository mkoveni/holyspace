package org.onechurch.childrenministry.messaging.application.command.recorddeliveryreceipt;
import java.util.UUID;
public record RecordDeliveryReceiptCommand(UUID actorId, UUID deliveryId, String messageId, String eventKey,
    String status, String reference) {}
