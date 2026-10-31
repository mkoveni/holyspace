package org.onechurch.identityaccess.application.command.createpermission;

import java.util.UUID;

public record CreatePermissionCommand(UUID actorId, String code, String description) {}
