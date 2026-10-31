package org.onechurch.events.application.contract;
import java.time.Instant;
import java.util.List;
import java.util.UUID;
public interface EventReportReader {
    record Row(UUID occurrenceId, UUID eventId, String title, Instant startsAt, Instant endsAt, String timeZone,
        String venue, String status, AttendanceReader.Counts counts) {}
    record Totals(long occurrences, long confirmed, long waitlisted, long cancelled, long attended, long absent) {}
    List<Row> rows(UUID event, String search, Instant from, Instant until, Instant now, int limit, int offset);
    Totals totals(UUID event, String search, Instant from, Instant until, Instant now);
}
