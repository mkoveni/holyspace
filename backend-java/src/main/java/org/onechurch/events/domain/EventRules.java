package org.onechurch.events.domain;
import java.time.DateTimeException;
import java.time.Duration;
import java.time.Instant;
import java.time.ZoneId;
import java.time.temporal.ChronoUnit;
import java.util.UUID;
public final class EventRules {
    private EventRules() {
    }
    public static String text(String value, int max) {
        if (value == null || value.isBlank() || value.length() > max) {
            throw EventException.invalid("Enter text of 1–" + max + " characters.");
        }
        return value.strip();
    }
    public static String optional(String value, int max) {
        if (value == null || value.isBlank()) {
            return "";
        }
        return text(value, max);
    }
    public static void id(UUID id) {
        if (id == null) {
            throw EventException.invalid("An identifier is required.");
        }
    }
    public static Instant instant(Instant at) {
        if (at == null) {
            throw EventException.invalid("A timestamp is required.");
        }
        return at.truncatedTo(ChronoUnit.MICROS);
    }
    public static void period(Instant from, Instant until) {
        if (!instant(until).isAfter(instant(from)) || Duration.between(from, until).compareTo(Duration.ofDays(7)) > 0) {
            throw EventException.invalid("Choose a positive interval of at most seven days.");
        }
    }
    public static boolean overlaps(Instant start, Instant end, Instant from, Instant until) {
        return start.isBefore(until) && from.isBefore(end);
    }
    public static void version(long actual, long expected) {
        if (actual != expected) {
            throw EventException.conflict("These details changed. Refresh and review before saving.");
        }
    }
    public static ZoneId zone(String value) {
        try {
            return ZoneId.of(value);
        } catch (DateTimeException | NullPointerException invalid) {
            throw EventException.invalid("Choose a valid time zone.");
        }
    }
    public static void page(int limit, int offset) {
        if (limit < 1 || limit > 100 || offset < 0 || offset > 100000) {
            throw EventException.invalid("Choose a page of 1–100 records.");
        }
    }
    public static void range(Instant from, Instant until) {
        if (!instant(until).isAfter(instant(from))
            || Duration.between(from, until).compareTo(Duration.ofDays(366)) > 0) {
            throw EventException.invalid("Choose a date range of at most 366 days.");
        }
    }
}
