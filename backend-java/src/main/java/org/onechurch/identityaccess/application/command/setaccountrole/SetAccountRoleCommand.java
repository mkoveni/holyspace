package org.onechurch.identityaccess.application.command.setaccountrole;

import java.util.UUID;

public record SetAccountRoleCommand(UUID actorId, UUID accountId, UUID roleId, boolean assign) {}
