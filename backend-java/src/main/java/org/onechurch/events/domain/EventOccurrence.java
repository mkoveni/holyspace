package org.onechurch.events.domain;
import java.time.Instant;
import java.time.LocalDate;
import java.util.Set;
import java.util.UUID;
public record EventOccurrence(UUID id, UUID eventId, UUID seriesId, LocalDate originDate, String timeZone,
    Instant startsAt, Instant endsAt, UUID venueId, String venueName, String venueAddress, boolean exclusive,
    String status, boolean overridden, long version, long scheduleRevision) {
    public EventOccurrence {
        EventRules.id(id);
        EventRules.id(eventId);
        EventRules.id(seriesId);
        EventRules.id(venueId);
        EventRules.zone(timeZone);
        startsAt = EventRules.instant(startsAt);
        endsAt = EventRules.instant(endsAt);
        EventRules.period(startsAt, endsAt);
        venueName = EventRules.text(venueName, 150);
        venueAddress = EventRules.optional(venueAddress, 1000);
        if (originDate == null || status == null || !Set.of("SCHEDULED", "CANCELLED").contains(status)) {
            throw EventException.invalid("Invalid occurrence state.");
        }
    }
    public EventOccurrence reschedule(EventSchedule.Window window, Venue venue, boolean override) {
        if (!status.equals("SCHEDULED")) {
            throw EventException.conflict("Restore the cancelled occurrence before rescheduling it.");
        }
        return new EventOccurrence(id, eventId, seriesId, originDate, timeZone, window.startsAt(), window.endsAt(),
            venue.id(), venue.name(), venue.address(), venue.exclusive(), status, override, version + 1,
            scheduleRevision + 1);
    }
    public EventOccurrence cancel() {
        if (status.equals("CANCELLED")) {
            throw EventException.conflict("This occurrence is already cancelled.");
        }
        return status("CANCELLED");
    }
    public EventOccurrence restore() {
        if (!status.equals("CANCELLED")) {
            throw EventException.conflict("Only cancelled occurrences can be restored.");
        }
        return status("SCHEDULED");
    }
    private EventOccurrence status(String next) {
        return new EventOccurrence(id, eventId, seriesId, originDate, timeZone, startsAt, endsAt, venueId, venueName,
            venueAddress, exclusive, next, true, version + 1, scheduleRevision + 1);
    }
}
