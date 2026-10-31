package org.onechurch.childrenministry.shared.domain;
import java.time.Instant;
import java.time.LocalDate;
import java.time.temporal.ChronoUnit;
public final class ChildrenRules {
    private ChildrenRules() {
    }
    public static Instant instant(Instant value) {
        return value == null ? null : value.truncatedTo(ChronoUnit.MICROS);
    }
    public static String text(String value, int max) {
        if (value == null || value.isBlank() || value.strip().length() > max) {
            throw ChildrenException.invalid("A value is missing or exceeds its maximum length of " + max + ".");
        }
        return value.strip();
    }
    public static String optional(String value, int max) {
        return value == null || value.isBlank() ? "" : text(value, max);
    }
    public static void period(Instant from, Instant until) {
        if (from == null || until == null || !until.isAfter(from)) {
            throw ChildrenException.invalid("Validity end must follow its start.");
        }
    }
    public static void period(LocalDate from, LocalDate until) {
        if (from == null || until == null || !until.isAfter(from)) {
            throw ChildrenException.invalid("Validity end must follow its start.");
        }
    }
    public static boolean covers(Instant start, Instant end, Instant from, Instant until) {
        return !start.isAfter(from) && from.isBefore(end) && !end.isBefore(until);
    }
    public static void version(long actual, long expected) {
        if (actual != expected || expected < 0) {
            throw ChildrenException.conflict("This record changed. Reload before trying again.");
        }
    }
    public static void child(LocalDate birth, LocalDate on) {
        if (birth == null || birth.isAfter(on) || !birth.plusYears(18).isAfter(on)) {
            throw ChildrenException.invalid("A recorded birth date identifying a child under 18 is required.");
        }
    }
    public static void adult(LocalDate birth, LocalDate on) {
        if (birth == null || birth.isAfter(on) || birth.plusYears(18).isAfter(on)) {
            throw ChildrenException.invalid("A recorded birth date confirming adulthood is required.");
        }
    }
    public static void page(int limit, int offset) {
        if (limit < 1 || limit > 100 || offset < 0 || offset > 1000000) {
            throw ChildrenException.invalid("Invalid paging parameters.");
        }
    }
}
