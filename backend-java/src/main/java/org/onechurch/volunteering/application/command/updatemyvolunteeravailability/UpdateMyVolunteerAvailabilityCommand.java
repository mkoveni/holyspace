package org.onechurch.volunteering.application.command.updatemyvolunteeravailability;
import org.onechurch.volunteering.application.VolunteerProfiles.ExceptionWindow;
import org.onechurch.volunteering.application.VolunteerProfiles.Weekly;

import java.util.List;
import java.util.UUID;
public record UpdateMyVolunteerAvailabilityCommand(UUID volunteerId, long expectedVersion, List<Weekly> weekly,
    List<ExceptionWindow> exceptions) {}
