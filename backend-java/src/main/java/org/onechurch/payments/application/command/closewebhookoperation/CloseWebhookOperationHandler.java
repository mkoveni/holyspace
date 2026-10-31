package org.onechurch.payments.application.command.closewebhookoperation;
import org.onechurch.payments.application.YocoWebhookManagement;
public final class CloseWebhookOperationHandler {
    private final YocoWebhookManagement service;
    public CloseWebhookOperationHandler(YocoWebhookManagement service) {
        this.service = service;
    }
    public void handle(CloseWebhookOperationCommand c) {
        service.close(c.actorId(), c.requestId(), c.expectedVersion(), c.reason());
    }
}
