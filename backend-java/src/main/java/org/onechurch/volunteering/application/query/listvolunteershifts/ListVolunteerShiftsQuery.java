package org.onechurch.volunteering.application.query.listvolunteershifts;

import java.time.Instant;
import java.util.UUID;

public record ListVolunteerShiftsQuery(Instant from, Instant until, UUID teamId, int limit, int offset) {}
