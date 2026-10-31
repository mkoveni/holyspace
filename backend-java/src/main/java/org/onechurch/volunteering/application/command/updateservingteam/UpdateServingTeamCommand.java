package org.onechurch.volunteering.application.command.updateservingteam;

import java.util.UUID;

public record UpdateServingTeamCommand(UUID actorId, UUID teamId, long expectedVersion, String name, boolean active) {}
