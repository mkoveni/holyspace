package org.onechurch.volunteering.application.query.getownvolunteerschedule;

import java.util.UUID;

public record GetOwnVolunteerScheduleQuery(UUID actorId, int limit, int offset) {}
