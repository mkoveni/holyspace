package org.onechurch.volunteering.application.command.bulkcreatevolunteershifts;

import org.onechurch.volunteering.application.RosterPlanning;

import java.util.List;
import java.util.UUID;

public record BulkCreateVolunteerShiftsCommand(UUID actorId, List<RosterPlanning.Draft> drafts) {}
