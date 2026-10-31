package org.onechurch.childrenministry.sessions.application;
import org.onechurch.childrenministry.sessions.domain.WeeklyRecurrence;

import java.util.UUID;
public record SessionSeriesDraft(String name, UUID sourceSessionId, long sourceVersion, WeeklyRecurrence recurrence) {
    public String fingerprint() {
        return name.strip() + "|" + sourceSessionId + "|" + sourceVersion + "|" + recurrence.firstDate() + "|"
            + recurrence.startsAt() + "|" + recurrence.endsAt() + "|" + recurrence.intervalWeeks() + "|"
            + recurrence.occurrences() + "|" + recurrence.excludedDates().stream().sorted().toList();
    }
}
