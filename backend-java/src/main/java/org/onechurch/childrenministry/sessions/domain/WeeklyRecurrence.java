package org.onechurch.childrenministry.sessions.domain;
import org.onechurch.childrenministry.shared.domain.ChildrenException;

import java.time.Instant;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.LocalTime;
import java.time.ZoneId;
import java.time.ZoneOffset;
import java.util.List;
import java.util.Set;
import java.util.stream.Collectors;
import java.util.stream.IntStream;
public record WeeklyRecurrence(LocalDate firstDate, LocalTime startsAt, LocalTime endsAt, int intervalWeeks,
    int occurrences, Set<LocalDate> excludedDates) {
    public WeeklyRecurrence {
        if (firstDate == null || startsAt == null || endsAt == null || !endsAt.isAfter(startsAt)
            || startsAt.getSecond() != 0 || endsAt.getSecond() != 0 || startsAt.getNano() != 0 || endsAt.getNano() != 0
            || intervalWeeks < 1 || intervalWeeks > 4 || occurrences < 1 || occurrences > 52
            || (long) intervalWeeks * (occurrences - 1) * 7 > 365) {
            throw ChildrenException.invalid(
                "Choose 1–52 weekly dates, every 1–4 weeks, within 366 days, with an end time after the start on the same day.");
        }
        excludedDates = excludedDates == null ? Set.of() : Set.copyOf(excludedDates);
        Set<LocalDate> dates = IntStream.range(0, occurrences)
            .mapToObj(index -> firstDate.plusWeeks((long) index * intervalWeeks))
            .collect(Collectors.toUnmodifiableSet());
        if (!dates.containsAll(excludedDates) || excludedDates.size() == occurrences) {
            throw ChildrenException.invalid("Skip only dates in this series and keep at least one session.");
        }
    }
    public record Slot(LocalDate date, Instant startsAt, Instant endsAt, boolean excluded) {}
    public List<Slot> slots(ZoneId zone) {
        return IntStream.range(0, occurrences).mapToObj(index -> {
            LocalDate date = firstDate.plusWeeks((long) index * intervalWeeks);
            return new Slot(date, resolve(date.atTime(startsAt), zone), resolve(date.atTime(endsAt), zone),
                excludedDates.contains(date));
        }).toList();
    }
    private static Instant resolve(LocalDateTime local, ZoneId zone) {
        List<ZoneOffset> offsets = zone.getRules().getValidOffsets(local);
        if (offsets.size() != 1) {
            throw ChildrenException.invalid(
                "The local time " + local + " is ambiguous or unavailable in " + zone + ". Choose another time.");
        }
        return local.toInstant(offsets.getFirst());
    }
}
