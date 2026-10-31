package org.onechurch.volunteering.application.command.updatevolunteershift;

import java.time.Instant;
import java.util.UUID;

public record UpdateVolunteerShiftCommand(UUID actorId, UUID id, long expectedVersion, Instant startsAt, Instant endsAt,
    int positions, String status) {}
