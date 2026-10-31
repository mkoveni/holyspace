package org.onechurch.childrenministry.safeguarding.application.command.revokepickupauthorization;

import java.util.UUID;
public record RevokePickupAuthorizationCommand(UUID actorId, UUID id, long expectedVersion, String reason) {}
