package org.onechurch.volunteering.application.command.updateservingrole;

import java.util.List;
import java.util.UUID;

public record UpdateServingRoleCommand(UUID actorId, UUID teamId, UUID roleId, long expectedVersion, String name,
    String childrenRole, boolean active, List<UUID> requiredCredentials) {}
