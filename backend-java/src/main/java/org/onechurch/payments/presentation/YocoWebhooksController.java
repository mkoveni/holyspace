package org.onechurch.payments.presentation;
import org.onechurch.payments.application.YocoWebhookManagement;
import org.onechurch.payments.application.command.closewebhookoperation.CloseWebhookOperationCommand;
import org.onechurch.payments.application.command.closewebhookoperation.CloseWebhookOperationHandler;
import org.onechurch.payments.application.command.registeryocowebhook.RegisterYocoWebhookCommand;
import org.onechurch.payments.application.command.registeryocowebhook.RegisterYocoWebhookHandler;
import org.onechurch.payments.application.command.removeyocowebhook.RemoveYocoWebhookCommand;
import org.onechurch.payments.application.command.removeyocowebhook.RemoveYocoWebhookHandler;
import org.onechurch.payments.application.query.listyocowebhooks.ListYocoWebhooksHandler;
import org.onechurch.payments.application.query.listyocowebhooks.ListYocoWebhooksQuery;
import org.onechurch.payments.presentation.api.YocoWebhooksApi;
import org.springframework.http.CacheControl;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
@PreAuthorize("hasAuthority('payments.configure')")
public class YocoWebhooksController implements YocoWebhooksApi {
    private final ListYocoWebhooksHandler list;
    private final RegisterYocoWebhookHandler register;
    private final RemoveYocoWebhookHandler remove;
    private final CloseWebhookOperationHandler close;
    public YocoWebhooksController(ListYocoWebhooksHandler list, RegisterYocoWebhookHandler register,
        RemoveYocoWebhookHandler remove, CloseWebhookOperationHandler close) {
        this.list = list;
        this.register = register;
        this.remove = remove;
        this.close = close;
    }
    public ResponseEntity<YocoWebhookManagement.Listing> list(String mode, OAuth2AuthenticatedPrincipal actor) {
        return ResponseEntity.ok()
            .cacheControl(CacheControl.noStore())
            .body(list.handle(new ListYocoWebhooksQuery(UUID.fromString(actor.getName()), mode)));
    }
    @Override
    public YocoWebhookManagement.Result register(Change b, OAuth2AuthenticatedPrincipal actor) {
        return register.handle(new RegisterYocoWebhookCommand(UUID.fromString(actor.getName()), b.requestId(), b.mode(),
            b.expectedVersion(), b.reason()));
    }
    @Override
    public YocoWebhookManagement.Result remove(String id, Change b, OAuth2AuthenticatedPrincipal actor) {
        return remove.handle(new RemoveYocoWebhookCommand(UUID.fromString(actor.getName()), b.requestId(), b.mode(),
            b.expectedVersion(), b.reason(), id));
    }
    @Override
    public void close(UUID id, Close b, OAuth2AuthenticatedPrincipal actor) {
        close.handle(
            new CloseWebhookOperationCommand(UUID.fromString(actor.getName()), id, b.expectedVersion(), b.reason()));
    }
}
