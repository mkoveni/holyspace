package org.onechurch.identityaccess.presentation;

import org.onechurch.identityaccess.application.command.createpermission.CreatePermissionCommand;
import org.onechurch.identityaccess.application.command.createpermission.CreatePermissionHandler;
import org.onechurch.identityaccess.application.command.createrole.CreateRoleCommand;
import org.onechurch.identityaccess.application.command.createrole.CreateRoleHandler;
import org.onechurch.identityaccess.application.command.setrolepermission.SetRolePermissionCommand;
import org.onechurch.identityaccess.application.command.setrolepermission.SetRolePermissionHandler;
import org.onechurch.identityaccess.application.contract.AccessReader.Permission;
import org.onechurch.identityaccess.application.contract.AccessReader.Role;
import org.onechurch.identityaccess.application.query.listpermissions.ListPermissionsHandler;
import org.onechurch.identityaccess.application.query.listpermissions.ListPermissionsQuery;
import org.onechurch.identityaccess.application.query.listroles.ListRolesHandler;
import org.onechurch.identityaccess.application.query.listroles.ListRolesQuery;
import org.onechurch.identityaccess.presentation.api.AccessApi;
import org.springframework.http.ResponseEntity;
import org.springframework.security.access.prepost.PreAuthorize;
import org.springframework.security.oauth2.core.OAuth2AuthenticatedPrincipal;
import org.springframework.web.bind.annotation.RestController;

import java.util.List;
import java.util.UUID;

@RestController
@PreAuthorize("hasAuthority('identity.manage')")
public class AccessController implements AccessApi {

    private final ListRolesHandler roles;
    private final ListPermissionsHandler permissions;
    private final CreateRoleHandler createRole;
    private final CreatePermissionHandler createPermission;
    private final SetRolePermissionHandler assign;

    public AccessController(ListRolesHandler roles, ListPermissionsHandler permissions, CreateRoleHandler createRole,
        CreatePermissionHandler createPermission, SetRolePermissionHandler assign) {
        this.roles = roles;
        this.permissions = permissions;
        this.createRole = createRole;
        this.createPermission = createPermission;
        this.assign = assign;
    }

    @Override
    public List<Role> roles() {
        return roles.handle(new ListRolesQuery());
    }

    @Override
    public List<Permission> permissions() {
        return permissions.handle(new ListPermissionsQuery());
    }

    @Override
    public Created createRole(OAuth2AuthenticatedPrincipal actor, RoleRequest request) {
        return new Created(createRole
            .handle(new CreateRoleCommand(UUID.fromString(actor.getName()), request.name(), request.description())));
    }

    @Override
    public Created createPermission(OAuth2AuthenticatedPrincipal actor, PermissionRequest request) {
        return new Created(createPermission.handle(
            new CreatePermissionCommand(UUID.fromString(actor.getName()), request.code(), request.description())));
    }

    @Override
    public ResponseEntity<Void> assign(OAuth2AuthenticatedPrincipal actor, UUID roleId, UUID permissionId) {
        assign.handle(new SetRolePermissionCommand(UUID.fromString(actor.getName()), roleId, permissionId, true));
        return ResponseEntity.noContent().build();
    }

    @Override
    public ResponseEntity<Void> remove(OAuth2AuthenticatedPrincipal actor, UUID roleId, UUID permissionId) {
        assign.handle(new SetRolePermissionCommand(UUID.fromString(actor.getName()), roleId, permissionId, false));
        return ResponseEntity.noContent().build();
    }
}
