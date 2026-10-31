package org.onechurch.giving.domain;
import java.time.LocalDate;
public final class GivingRules {
    private GivingRules() {
    }
    public static String text(String value, int max, String label) {
        if (value == null || value.isBlank() || value.trim().length() > max) {
            throw GivingException.invalid(label + " is required and must be at most " + max + " characters.");
        }
        return value.trim();
    }
    public static void version(long actual, long expected) {
        if (actual != expected) {
            throw GivingException.conflict("This record changed. Refresh before trying again.");
        }
    }
    public static void date(LocalDate date, LocalDate today) {
        if (date == null || date.isAfter(today) || date.isBefore(LocalDate.of(1900, 1, 1))) {
            throw GivingException.invalid("Choose a date from 1900 through today.");
        }
    }
    public static void page(int limit, int offset) {
        if (limit < 1 || limit > 100 || offset < 0 || offset > 100000) {
            throw GivingException.invalid("Use a page size of 1–100 and an offset of 0–100000.");
        }
    }
}
