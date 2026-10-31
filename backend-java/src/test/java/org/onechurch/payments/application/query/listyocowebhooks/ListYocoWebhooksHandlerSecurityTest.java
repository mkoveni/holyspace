package org.onechurch.payments.application.query.listyocowebhooks;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class ListYocoWebhooksHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return ListYocoWebhooksHandler.class;
    }
    protected Object input(UUID actor) {
        return new ListYocoWebhooksQuery(actor, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
