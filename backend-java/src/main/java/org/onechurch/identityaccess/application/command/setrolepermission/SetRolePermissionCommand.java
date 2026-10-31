package org.onechurch.identityaccess.application.command.setrolepermission;

import java.util.UUID;

public record SetRolePermissionCommand(UUID actorId, UUID roleId, UUID permissionId, boolean assign) {}
