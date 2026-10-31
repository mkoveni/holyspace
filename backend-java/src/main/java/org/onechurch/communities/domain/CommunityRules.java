package org.onechurch.communities.domain;
import java.time.Instant;
import java.time.temporal.ChronoUnit;
public final class CommunityRules {
    private CommunityRules() {
    }
    public static String text(String value, int max) {
        if (value == null || value.isBlank() || value.length() > max) {
            throw CommunityException.invalid("Enter text of 1–" + max + " characters.");
        }
        return value.strip();
    }
    public static String optional(String value, int max) {
        return value == null || value.isBlank() ? "" : text(value, max);
    }
    public static Instant time(Instant value) {
        return value.truncatedTo(ChronoUnit.MICROS);
    }
    public static void version(long actual, long expected) {
        if (actual != expected) {
            throw CommunityException.conflict("These details changed. Refresh and review before saving.");
        }
    }
    public static void page(int limit, int offset) {
        if (limit < 1 || limit > 100 || offset < 0 || offset > 100000) {
            throw CommunityException.invalid("Choose a page of 1–100 records.");
        }
    }
}
