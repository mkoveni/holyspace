package org.onechurch.childrenministry.messaging.application.query.getemaildelivery;
import java.util.UUID;
public record GetEmailDeliveryQuery(UUID deliveryId, int limit, int offset) {}
