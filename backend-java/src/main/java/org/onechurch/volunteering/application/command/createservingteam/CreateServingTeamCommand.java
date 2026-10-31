package org.onechurch.volunteering.application.command.createservingteam;

import java.util.UUID;

public record CreateServingTeamCommand(UUID actorId, String name) {}
