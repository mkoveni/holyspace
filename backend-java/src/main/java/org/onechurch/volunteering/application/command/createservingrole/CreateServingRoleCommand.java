package org.onechurch.volunteering.application.command.createservingrole;

import java.util.List;
import java.util.UUID;

public record CreateServingRoleCommand(UUID actorId, UUID teamId, long expectedVersion, String name,
    String childrenRole, List<UUID> requiredCredentials) {}
