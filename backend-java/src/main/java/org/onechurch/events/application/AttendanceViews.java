package org.onechurch.events.application;
import org.onechurch.events.application.contract.AttendanceReader;
import org.onechurch.events.application.contract.OccurrenceReader;
import org.onechurch.events.domain.AdmissionWindow;
import org.onechurch.events.domain.EventException;
import org.onechurch.events.domain.EventOccurrence;
import org.onechurch.events.domain.EventRules;

import java.time.Clock;
import java.time.Instant;
import java.util.List;
import java.util.UUID;
public final class AttendanceViews {
    private final AttendanceReader attendance;
    private final OccurrenceReader occurrences;
    private final Clock clock;
    public AttendanceViews(AttendanceReader attendance, OccurrenceReader occurrences, Clock clock) {
        this.attendance = attendance;
        this.occurrences = occurrences;
        this.clock = clock;
    }
    public AttendanceReader.Page roster(UUID id, String search, String status, int limit, int offset) {
        EventRules.page(limit, offset);
        if (status != null && !List.of("PRESENT", "EXPECTED", "ABSENT", "WAITLISTED", "CANCELLED").contains(status)) {
            throw EventException.invalid("Choose an attendance status.");
        }
        String term = EventRules.optional(search, 200);
        EventOccurrence occurrence = occurrences.occurrence(id).orElseThrow(() -> EventException.missing("Occurrence"));
        Instant now = clock.instant();
        return new AttendanceReader.Page(attendance.roster(id, term, status, now, limit, offset),
            attendance.rosterCount(id, term, status, now), attendance.counts(id, now),
            AdmissionWindow.open(occurrence, now));
    }
    public List<AttendanceReader.History> history(UUID registration, int limit, int offset) {
        EventRules.page(limit, offset);
        return attendance.forRegistration(registration)
            .map(a -> attendance.history(a.id(), limit, offset))
            .orElseThrow(() -> EventException.missing("Attendance"));
    }
}
