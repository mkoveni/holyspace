package org.onechurch.childrenministry.sessions.application;
import org.onechurch.childrenministry.sessions.domain.WeeklyRecurrence;

import java.time.Instant;
import java.util.List;
import java.util.UUID;
public record SessionSeriesPreview(String className, String roomName, String timeZone, int seatLimit,
    List<TeamMember> team, List<Occurrence> occurrences, Instant checkedAt) {
    public record TeamMember(UUID workerId, String name, String role) {}
    public record Occurrence(WeeklyRecurrence.Slot slot, List<String> conflicts, List<String> staffingWarnings) {}
}
