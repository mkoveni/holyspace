package org.onechurch.events.application;
import org.onechurch.events.application.contract.OccurrenceReader;
import org.onechurch.events.application.contract.VenueReader;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.EventRules;
import org.onechurch.events.domain.EventSchedule;
import org.onechurch.events.domain.EventSeries;
import org.onechurch.events.domain.Venue;

import java.time.Clock;
import java.time.LocalDate;
import java.time.LocalTime;
import java.util.List;
import java.util.UUID;
public final class ScheduleReview {
    public record Draft(UUID venueId, String timeZone, String frequency, int interval, int count, LocalDate firstDate,
        LocalTime startsAt, LocalTime endsAt, int endDayOffset) {
        public EventSeries series(UUID id, UUID event) {
            return new EventSeries(id, event, timeZone, frequency, interval, count, firstDate, startsAt, endsAt,
                endDayOffset, venueId, 0);
        }
    }
    public record Preview(String hash, Venue venue, List<EventSchedule.Window> dates) {}
    private final VenueReader venues;
    private final OccurrenceReader occurrences;
    private final Clock clock;
    public ScheduleReview(VenueReader venues, OccurrenceReader occurrences, Clock clock) {
        this.venues = venues;
        this.occurrences = occurrences;
        this.clock = clock;
    }
    public Preview preview(Draft draft) {
        if (draft == null) {
            throw EventException.invalid("Choose a schedule.");
        }
        EventRules.id(draft.venueId());
        Venue venue = venues.venue(draft.venueId())
            .filter(Venue::active)
            .orElseThrow(() -> EventException.missing("Active venue"));
        EventSeries series = draft.series(new UUID(0, 0), new UUID(0, 0));
        List<EventSchedule.Window> dates = EventSchedule.preview(series);
        for (EventSchedule.Window date : dates) {
            if (!date.startsAt().isAfter(clock.instant())) {
                throw EventException.invalid("New occurrences must start in the future.");
            }
            if (venue.exclusive() && occurrences.venueClash(venue.id(), date.startsAt(), date.endsAt(), List.of())) {
                throw EventException.conflict("The exclusive venue is already reserved during a selected date.");
            }
        }
        if (venue.exclusive()) {
            for (int index = 1; index < dates.size(); index++) {
                if (dates.get(index).startsAt().isBefore(dates.get(index - 1).endsAt())) {
                    throw EventException.conflict("The recurring dates overlap in the exclusive venue.");
                }
            }
        }
        return new Preview(Fingerprint.of(draft, venue, dates), venue, dates);
    }
}
