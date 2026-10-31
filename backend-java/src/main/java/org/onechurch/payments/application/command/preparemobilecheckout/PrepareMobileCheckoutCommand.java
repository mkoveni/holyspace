package org.onechurch.payments.application.command.preparemobilecheckout;
import java.util.UUID;
public record PrepareMobileCheckoutCommand(UUID actorId, UUID orderId) {}
