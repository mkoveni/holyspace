package org.onechurch.volunteering.domain;

import java.time.Instant;
import java.time.LocalDate;
import java.time.LocalDateTime;
import java.time.ZoneId;
import java.util.ArrayList;
import java.util.Comparator;
import java.util.List;

public final class Availability {
    private Availability() {
    }
    private record Window(Instant from, Instant until) {}
    public static boolean covers(Instant from, Instant until, List<AvailabilityRule> rules,
        List<AvailabilityException> exceptions) {
        if (exceptions.stream()
            .anyMatch(e -> !e.available() && ServingRules.overlaps(e.startsAt(), e.endsAt(), from, until))) {
            return false;
        }
        List<Window> windows = new ArrayList<>();
        exceptions.stream()
            .filter(AvailabilityException::available)
            .forEach(e -> windows.add(new Window(e.startsAt(), e.endsAt())));
        for (AvailabilityRule rule : rules) {
            ZoneId zone = ServingRules.zone(rule.timeZone());
            LocalDate first = from.atZone(zone).toLocalDate().minusDays(1), last = until.atZone(zone).toLocalDate();
            for (LocalDate day = first; !day.isAfter(last); day = day.plusDays(1)) {
                if (day.getDayOfWeek().getValue() != rule.dayOfWeek() || day.isBefore(rule.validFrom())
                    || !day.isBefore(rule.validUntil())) {
                    continue;
                }
                LocalDateTime start = day.atTime(rule.startsAt()),
                    end = (rule.endsAt().isAfter(rule.startsAt()) ? day : day.plusDays(1)).atTime(rule.endsAt());
                if (zone.getRules().getValidOffsets(start).size() != 1
                    || zone.getRules().getValidOffsets(end).size() != 1) {
                    continue;
                }
                windows.add(new Window(start.atZone(zone).toInstant(), end.atZone(zone).toInstant()));
            }
        }
        windows.sort(Comparator.comparing(Window::from));
        Instant cursor = from;
        for (Window window : windows) {
            if (window.until().isBefore(cursor)) {
                continue;
            }
            if (window.from().isAfter(cursor)) {
                break;
            }
            if (window.until().isAfter(cursor)) {
                cursor = window.until();
            }
            if (!cursor.isBefore(until)) {
                return true;
            }
        }
        return false;
    }
}
