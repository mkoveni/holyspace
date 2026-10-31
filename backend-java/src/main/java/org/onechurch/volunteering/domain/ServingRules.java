package org.onechurch.volunteering.domain;

import java.time.DateTimeException;
import java.time.Instant;
import java.time.ZoneId;
import java.time.temporal.ChronoUnit;
import java.util.UUID;

public final class ServingRules {
    private ServingRules() {
    }
    public static String text(String value, int max) {
        if (value == null || value.isBlank() || value.length() > max) {
            throw VolunteerException.invalid("Enter text of at most " + max + " characters.");
        }
        return value.strip();
    }
    public static Instant instant(Instant value) {
        if (value == null) {
            throw VolunteerException.invalid("A timestamp is required.");
        }
        return value.truncatedTo(ChronoUnit.MICROS);
    }
    public static void period(Instant from, Instant until) {
        if (!instant(until).isAfter(instant(from))) {
            throw VolunteerException.invalid("The end must follow the start.");
        }
    }
    public static boolean covers(Instant from, Instant until, Instant start, Instant end) {
        return !from.isAfter(start) && !until.isBefore(end);
    }
    public static boolean overlaps(Instant from, Instant until, Instant start, Instant end) {
        return from.isBefore(end) && start.isBefore(until);
    }
    public static void version(long actual, long expected) {
        if (actual != expected) {
            throw VolunteerException.conflict("This record changed. Refresh before saving.");
        }
    }
    public static void id(UUID id) {
        if (id == null) {
            throw VolunteerException.invalid("An identifier is required.");
        }
    }
    public static ZoneId zone(String value) {
        try {
            return ZoneId.of(value);
        } catch (DateTimeException | NullPointerException invalid) {
            throw VolunteerException.invalid("Choose a valid time zone.");
        }
    }
}
