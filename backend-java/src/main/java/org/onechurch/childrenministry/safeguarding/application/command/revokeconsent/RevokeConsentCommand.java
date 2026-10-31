package org.onechurch.childrenministry.safeguarding.application.command.revokeconsent;

import java.util.UUID;
public record RevokeConsentCommand(UUID actorId, UUID id, long expectedVersion, String reason) {}
