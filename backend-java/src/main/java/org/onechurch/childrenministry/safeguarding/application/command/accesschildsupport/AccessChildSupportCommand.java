package org.onechurch.childrenministry.safeguarding.application.command.accesschildsupport;

import java.util.UUID;
public record AccessChildSupportCommand(UUID actorId, UUID childId, UUID sessionId) {}
