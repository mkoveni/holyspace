package org.onechurch.people.application.command.setpersonarchived;

import java.util.UUID;

public record SetPersonArchivedCommand(UUID actorId, UUID personId, long expectedVersion, boolean archived) {}
