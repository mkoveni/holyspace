package org.onechurch.identityaccess.application.command.setrolepermission;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.identityaccess.domain.IdentityException;
import org.onechurch.shared.infrastructure.IdentityIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.util.List;
import java.util.Map;
import java.util.UUID;

class SetRolePermissionHandlerIntegrationTests extends IdentityIntegrationTestSupport {

    @Autowired private SetRolePermissionHandler handler;

    @Test
    void permissionAndRoleChangesTakeEffectOnExistingTokens() throws Exception {
        UUID id = createUser("coordinator", "coordinator@church.org", "Coordinator");
        String token = login("coordinator", PASSWORD);
        UUID role = created(request("POST", "/api/roles", admin,
            Map.of("name", "coordinator", "description", "Coordinates volunteers")));
        UUID permission = created(request("POST", "/api/permissions", admin,
            Map.of("code", "volunteering.manage", "description", "Manage volunteering")));
        assertThat(request("POST", "/api/roles/" + role + "/permissions/" + permission, admin, null).status())
            .isEqualTo(204);
        assertThat(request("POST", "/api/users/" + id + "/roles/" + role, admin, null).status()).isEqualTo(204);
        var me = request("GET", "/api/me", token, null);
        assertThat((List<String>) me.data().get("permissions")).contains("volunteering.manage", "volunteering.read",
            "volunteering.serve");
        assertThat(request("DELETE", "/api/roles/" + role + "/permissions/" + permission, admin, null).status())
            .isEqualTo(204);
        assertThat((List<String>) request("GET", "/api/me", token, null).data().get("permissions"))
            .doesNotContain("volunteering.manage", "volunteering.read", "volunteering.serve");
        assertThat(request("POST", "/api/users/" + id + "/roles/" + ADMIN_ROLE, admin, null).status()).isEqualTo(204);
        assertThat(request("GET", "/api/users", token, null).status()).isEqualTo(200);
        assertThat(request("DELETE", "/api/users/" + id + "/roles/" + ADMIN_ROLE, admin, null).status()).isEqualTo(204);
        assertThat(request("GET", "/api/users", token, null).status()).isEqualTo(403);
    }

    @Test
    void refusesToRemoveTheLastManagementPermissionAndRollsBack() throws Exception {
        assertThat(
            request("DELETE", "/api/roles/" + ADMIN_ROLE + "/permissions/" + MANAGE_PERMISSION, admin, null).status())
            .isEqualTo(409);
        assertThat(request("GET", "/api/users", admin, null).status()).isEqualTo(200);
    }

    @Test
    void directCallsRequireAuthenticationAndRejectMissingPermissionsAndActorSpoofing() throws Exception {
        UUID helperId = createUser("direct-helper", "direct-helper@church.org", "Direct Helper");
        String helper = login("direct-helper", PASSWORD);
        UUID roleId = created(request("POST", "/api/roles", admin, Map.of("name", "direct-role")));
        assertIdentityFailure(
            () -> handler.handle(new SetRolePermissionCommand(adminId, roleId, MANAGE_PERMISSION, true)),
            IdentityException.Kind.UNAUTHENTICATED);
        asActor(helper, () -> {
            assertIdentityFailure(
                () -> handler.handle(new SetRolePermissionCommand(adminId, roleId, MANAGE_PERMISSION, true)),
                IdentityException.Kind.FORBIDDEN);
            assertIdentityFailure(
                () -> handler.handle(new SetRolePermissionCommand(helperId, roleId, MANAGE_PERMISSION, true)),
                IdentityException.Kind.FORBIDDEN);
            return null;
        });
        asActor(admin, () -> {
            handler.handle(new SetRolePermissionCommand(adminId, roleId, MANAGE_PERMISSION, true));
            return null;
        });
    }

}
