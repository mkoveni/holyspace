package org.onechurch.people.application.command.endcontact;

import java.util.UUID;

public record EndContactCommand(UUID actorId, UUID personId, long expectedVersion, UUID contactId) {}
