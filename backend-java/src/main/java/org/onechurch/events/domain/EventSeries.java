package org.onechurch.events.domain;
import java.time.LocalDate;
import java.time.LocalTime;
import java.util.Set;
import java.util.UUID;
public record EventSeries(UUID id, UUID eventId, String timeZone, String frequency, int interval, int count,
    LocalDate firstDate, LocalTime startsAt, LocalTime endsAt, int endDayOffset, UUID venueId, long version) {
    public EventSeries {
        startsAt = startsAt == null ? null : startsAt.truncatedTo(java.time.temporal.ChronoUnit.MICROS);
        endsAt = endsAt == null ? null : endsAt.truncatedTo(java.time.temporal.ChronoUnit.MICROS);
        EventRules.id(id);
        EventRules.id(eventId);
        EventRules.id(venueId);
        EventRules.zone(timeZone);
        if (frequency == null || !Set.of("ONCE", "DAILY", "WEEKLY", "MONTHLY").contains(frequency) || interval < 1
            || interval > 52 || count < 1 || count > 104 || firstDate == null || startsAt == null || endsAt == null
            || endDayOffset < 0 || endDayOffset > 7 || (frequency.equals("ONCE") && count != 1)) {
            throw EventException.invalid("Choose a recurrence, 1–104 dates, times and an end day offset of 0–7.");
        }
    }
    public EventSeries defaults(LocalTime start, LocalTime end, int offset, UUID venue) {
        return new EventSeries(id, eventId, timeZone, frequency, interval, count, firstDate, start, end, offset, venue,
            version + 1);
    }
    public EventSeries touch() {
        return defaults(startsAt, endsAt, endDayOffset, venueId);
    }
}
