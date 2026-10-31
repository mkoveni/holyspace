package org.onechurch.identityaccess.application.command.setaccountstatus;

import java.util.UUID;

public record SetAccountStatusCommand(UUID actorId, UUID id, boolean enabled) {}
