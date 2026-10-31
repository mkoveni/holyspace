package org.onechurch.volunteering.domain;

import java.time.LocalDate;
import java.time.LocalTime;
import java.util.UUID;

public record AvailabilityRule(UUID id, UUID volunteerId, long revision, int dayOfWeek, LocalTime startsAt,
    LocalTime endsAt, String timeZone, LocalDate validFrom, LocalDate validUntil) {
    public AvailabilityRule {
        if (dayOfWeek < 1 || dayOfWeek > 7 || startsAt == null || endsAt == null || endsAt.equals(startsAt)
            || validFrom == null || validUntil == null || !validUntil.isAfter(validFrom)) {
            throw VolunteerException.invalid(
                "Use a weekly day, start/end time and an exclusive end date. Overnight windows end on the following day.");
        }
        ServingRules.zone(timeZone);
    }
}
