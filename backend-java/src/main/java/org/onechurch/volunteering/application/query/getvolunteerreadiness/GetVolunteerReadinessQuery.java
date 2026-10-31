package org.onechurch.volunteering.application.query.getvolunteerreadiness;

import java.util.UUID;

public record GetVolunteerReadinessQuery(UUID volunteerId, UUID shiftId) {}
