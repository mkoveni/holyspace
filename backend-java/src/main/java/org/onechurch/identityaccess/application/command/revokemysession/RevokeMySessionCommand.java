package org.onechurch.identityaccess.application.command.revokemysession;
import java.util.UUID;
public record RevokeMySessionCommand(UUID actorId, UUID sessionId) {}
