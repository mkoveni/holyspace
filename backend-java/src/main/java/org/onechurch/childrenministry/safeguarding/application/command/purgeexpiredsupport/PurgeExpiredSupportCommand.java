package org.onechurch.childrenministry.safeguarding.application.command.purgeexpiredsupport;

import java.util.UUID;
public record PurgeExpiredSupportCommand(UUID actorId, int limit) {}
