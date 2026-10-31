package org.onechurch.payments.application.command.recordrefund;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class RecordRefundHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return RecordRefundHandler.class;
    }
    protected Object input(UUID actor) {
        return new RecordRefundCommand(actor, null, null, null, 0, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
