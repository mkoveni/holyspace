package org.onechurch.volunteering.application.command.createvolunteershift;

import org.onechurch.volunteering.application.RosterPlanning;

import java.util.UUID;

public record CreateVolunteerShiftCommand(UUID actorId, RosterPlanning.Draft draft) {}
