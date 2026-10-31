package org.onechurch.payments.application.command.removeyocowebhook;
import org.onechurch.payments.application.YocoWebhookManagement;
public final class RemoveYocoWebhookHandler {
    private final YocoWebhookManagement service;
    public RemoveYocoWebhookHandler(YocoWebhookManagement service) {
        this.service = service;
    }
    public YocoWebhookManagement.Result handle(RemoveYocoWebhookCommand c) {
        return service.change(c.actorId(), c.requestId(), c.mode(), c.expectedVersion(), "REMOVE", c.subscriptionId(),
            c.reason());
    }
}
