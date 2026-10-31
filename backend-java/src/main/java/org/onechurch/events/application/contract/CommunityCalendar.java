package org.onechurch.events.application.contract;
import java.time.Instant;
import java.time.LocalDate;
import java.time.LocalTime;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface CommunityCalendar {
    final class Failure extends RuntimeException {
        private final int status;
        public Failure(int status, String message) {
            super(message);
            this.status = status;
        }
        public int status() {
            return status;
        }
    }

    record Schedule(UUID venueId, String timeZone, String frequency, int interval, int count, LocalDate firstDate,
        LocalTime startsAt, LocalTime endsAt, int endDayOffset) {}
    record Date(UUID id, Instant startsAt, Instant endsAt, String timeZone, String venue, String status,
        long revision) {}
    record Preview(String hash, List<Date> dates) {}
    Preview preview(Schedule schedule);
    List<Date> create(UUID actor, UUID request, String name, Schedule schedule, String hash);
    Optional<Date> get(UUID occurrence);
    default List<Date> dates(List<UUID> occurrences) {
        return occurrences.stream().map(this::get).flatMap(Optional::stream).toList();
    }
    Optional<Date> lock(UUID occurrence);
}
