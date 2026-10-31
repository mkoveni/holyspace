package org.onechurch.volunteering.application.command.linkvolunteeraccount;

import java.util.UUID;

public record LinkVolunteerAccountCommand(UUID actorId, UUID volunteerId, long expectedVersion, UUID accountId) {}
