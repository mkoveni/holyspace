package org.onechurch.volunteering.application.query.reviewvolunteerreminder;

import java.util.UUID;

public record ReviewVolunteerReminderQuery(UUID assignmentId, UUID contactId, String message) {}
