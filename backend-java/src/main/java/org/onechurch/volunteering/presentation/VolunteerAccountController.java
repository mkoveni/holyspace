package org.onechurch.volunteering.presentation;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.command.linkvolunteeraccount.LinkVolunteerAccountCommand;
import org.onechurch.volunteering.application.command.linkvolunteeraccount.LinkVolunteerAccountHandler;
import org.onechurch.volunteering.presentation.api.VolunteerAccountApi;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;

@RestController
public class VolunteerAccountController implements VolunteerAccountApi {
    private final PermissionChecker auth;
    private final LinkVolunteerAccountHandler handler;
    public VolunteerAccountController(PermissionChecker auth, LinkVolunteerAccountHandler handler) {
        this.auth = auth;
        this.handler = handler;
    }
    public void link(UUID id, Link b, OAuth2AuthenticatedPrincipal p) {
        auth.requirePermission("volunteering.manage");
        auth.requirePermission("identity.manage");
        handler.handle(
            new LinkVolunteerAccountCommand(UUID.fromString(p.getName()), id, b.expectedVersion(), b.accountId()));
    }
}
