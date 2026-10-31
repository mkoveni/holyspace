package org.onechurch.volunteering.application.command.setvolunteeravailability;

import org.onechurch.volunteering.application.VolunteerProfiles;

import java.util.List;
import java.util.UUID;

public record SetVolunteerAvailabilityCommand(UUID actorId, UUID volunteerId, long expectedVersion,
    List<VolunteerProfiles.Weekly> weekly, List<VolunteerProfiles.ExceptionWindow> exceptions) {}
