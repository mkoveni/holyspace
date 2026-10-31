package org.onechurch.volunteering.presentation;

import org.onechurch.identityaccess.application.contract.PermissionChecker;
import org.onechurch.volunteering.application.command.updateservingrole.UpdateServingRoleCommand;
import org.onechurch.volunteering.application.command.updateservingrole.UpdateServingRoleHandler;
import org.onechurch.volunteering.presentation.api.ServingRolesApi;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.UUID;

@RestController
public class ServingRolesController implements ServingRolesApi {
    private final PermissionChecker auth;
    private final UpdateServingRoleHandler handler;
    public ServingRolesController(PermissionChecker auth, UpdateServingRoleHandler handler) {
        this.auth = auth;
        this.handler = handler;
    }
    public void edit(UUID team, UUID role, Edit b, OAuth2AuthenticatedPrincipal p) {
        auth.requirePermission("volunteering.manage");
        handler.handle(new UpdateServingRoleCommand(UUID.fromString(p.getName()), team, role, b.expectedVersion(),
            b.name(), b.childrenRole(), b.active(), b.requiredCredentials()));
    }
}
