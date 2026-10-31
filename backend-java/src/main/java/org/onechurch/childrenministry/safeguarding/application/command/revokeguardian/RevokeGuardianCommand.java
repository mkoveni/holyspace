package org.onechurch.childrenministry.safeguarding.application.command.revokeguardian;

import java.util.UUID;
public record RevokeGuardianCommand(UUID actorId, UUID id, long expectedVersion, String reason) {}
