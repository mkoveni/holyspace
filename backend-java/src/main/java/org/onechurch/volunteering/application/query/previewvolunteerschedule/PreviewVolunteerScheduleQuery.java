package org.onechurch.volunteering.application.query.previewvolunteerschedule;

import java.time.LocalDate;
import java.time.LocalTime;
import java.util.List;
import java.util.UUID;

public record PreviewVolunteerScheduleQuery(UUID requestId, UUID teamId, UUID roleId, List<LocalDate> dates,
    LocalTime startsAt, LocalTime endsAt, String timeZone, int positions) {}
