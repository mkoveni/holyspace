package org.onechurch.identityaccess.application.command.logout;

import java.util.UUID;

public record LogoutCommand(UUID accountId, UUID sessionId) {}
