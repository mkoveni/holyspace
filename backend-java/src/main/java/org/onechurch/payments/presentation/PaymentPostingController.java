package org.onechurch.payments.presentation;
import org.onechurch.payments.application.command.retrypaymentposting.RetryPaymentPostingCommand;
import org.onechurch.payments.application.command.retrypaymentposting.RetryPaymentPostingHandler;
import org.onechurch.payments.presentation.api.PaymentPostingApi;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
public class PaymentPostingController implements PaymentPostingApi {
    private final RetryPaymentPostingHandler handler;
    public PaymentPostingController(RetryPaymentPostingHandler handler) {
        this.handler = handler;
    }
    @Override
    @PreAuthorize("hasAuthority('payments.manage')")
    public RetryPaymentPostingHandler.Result retry(UUID orderId, Body body, OAuth2AuthenticatedPrincipal actor) {
        return handler
            .handle(new RetryPaymentPostingCommand(UUID.fromString(actor.getName()), orderId, body.expectedVersion()));
    }
}
