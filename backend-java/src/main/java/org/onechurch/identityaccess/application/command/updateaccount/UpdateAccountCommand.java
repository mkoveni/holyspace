package org.onechurch.identityaccess.application.command.updateaccount;

import java.util.UUID;

public record UpdateAccountCommand(UUID actorId, UUID id, String email, String displayName) {}
