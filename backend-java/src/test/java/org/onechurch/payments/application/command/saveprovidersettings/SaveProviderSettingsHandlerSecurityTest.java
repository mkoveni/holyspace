package org.onechurch.payments.application.command.saveprovidersettings;
import org.onechurch.shared.infrastructure.PaymentHandlerSecuritySupport;

import java.util.UUID;
class SaveProviderSettingsHandlerSecurityTest extends PaymentHandlerSecuritySupport {
    protected Class<?> handlerType() {
        return SaveProviderSettingsHandler.class;
    }
    protected Object input(UUID actor) {
        return new SaveProviderSettingsCommand(actor, null, null, 0, false, false, null, null, null, null, null);
    }
    @org.junit.jupiter.api.Test
    void rejectsSpoofedActor() {
        assertSpoofedActor();
    }
}
