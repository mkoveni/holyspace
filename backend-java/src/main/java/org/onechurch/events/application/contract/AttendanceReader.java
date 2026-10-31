package org.onechurch.events.application.contract;
import org.onechurch.events.domain.Attendance;
import org.onechurch.events.domain.EventRegistration;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface AttendanceReader {
    record Entry(EventRegistration registration, Attendance attendance, String name, String status) {}
    record Counts(long confirmed, long waitlisted, long cancelled, long attended, long absent) {}
    record Page(List<Entry> attendees, long total, Counts counts, boolean admissionOpen) {}
    record History(UUID id, UUID actorId, String action, String reason, Instant occurredAt) {}
    Optional<Attendance> forRegistration(UUID registration);
    List<Entry> roster(UUID occurrence, String search, String status, Instant now, int limit, int offset);
    long rosterCount(UUID occurrence, String search, String status, Instant now);
    Counts counts(UUID occurrence, Instant now);
    List<History> history(UUID attendance, int limit, int offset);
}
