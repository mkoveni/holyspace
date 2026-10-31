package org.onechurch.identityaccess.presentation;
import org.onechurch.identityaccess.application.command.revokemysession.RevokeMySessionCommand;
import org.onechurch.identityaccess.application.command.revokemysession.RevokeMySessionHandler;
import org.onechurch.identityaccess.application.query.listmysessions.ListMySessionsHandler;
import org.onechurch.identityaccess.application.query.listmysessions.ListMySessionsQuery;
import org.onechurch.identityaccess.presentation.api.DeviceSessionsApi;
import org.springframework.http.CacheControl;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;
@RestController
@PreAuthorize("isAuthenticated()")
public class DeviceSessionsController implements DeviceSessionsApi {
    private final ListMySessionsHandler list;
    private final RevokeMySessionHandler revoke;
    public DeviceSessionsController(ListMySessionsHandler list, RevokeMySessionHandler revoke) {
        this.list = list;
        this.revoke = revoke;
    }
    @Override
    public ResponseEntity<ListMySessionsHandler.Page> list(int limit, int offset) {
        return ResponseEntity.ok()
            .cacheControl(CacheControl.noStore())
            .body(list.handle(new ListMySessionsQuery(limit, offset)));
    }
    @Override
    public void revoke(UUID sessionId, OAuth2AuthenticatedPrincipal actor) {
        revoke.handle(new RevokeMySessionCommand(UUID.fromString(actor.getName()), sessionId));
    }
}
