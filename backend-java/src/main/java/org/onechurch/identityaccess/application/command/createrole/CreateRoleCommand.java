package org.onechurch.identityaccess.application.command.createrole;

import java.util.UUID;

public record CreateRoleCommand(UUID actorId, String name, String description) {}
