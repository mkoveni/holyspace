package org.onechurch.volunteering.application.query.getassignmenthistory;

import java.util.UUID;

public record GetAssignmentHistoryQuery(UUID id, int limit, int offset) {}
