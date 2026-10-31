package org.onechurch.payments.application.command.registeryocowebhook;
import org.onechurch.payments.application.YocoWebhookManagement;
public final class RegisterYocoWebhookHandler {
    private final YocoWebhookManagement service;
    public RegisterYocoWebhookHandler(YocoWebhookManagement service) {
        this.service = service;
    }
    public YocoWebhookManagement.Result handle(RegisterYocoWebhookCommand c) {
        return service.change(c.actorId(), c.requestId(), c.mode(), c.expectedVersion(), "REGISTER", null, c.reason());
    }
}
