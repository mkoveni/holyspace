package org.onechurch.payments.presentation;
import org.onechurch.payments.application.command.processnotification.ProcessNotificationCommand;
import org.onechurch.payments.application.command.processnotification.ProcessNotificationHandler;
import org.onechurch.payments.presentation.api.PaymentWebhooksApi;
import org.springframework.web.bind.annotation.RestController;

import jakarta.servlet.http.HttpServletRequest;

import java.util.Map;
@RestController
public class PaymentWebhooksController implements PaymentWebhooksApi {
    private final ProcessNotificationHandler handler;
    public PaymentWebhooksController(ProcessNotificationHandler handler) {
        this.handler = handler;
    }
    @Override
    public void receive(String provider, String body, Map<String, String> headers, HttpServletRequest request) {
        handler.handle(new ProcessNotificationCommand(provider, body, headers, request.getRemoteAddr()));
    }
}
