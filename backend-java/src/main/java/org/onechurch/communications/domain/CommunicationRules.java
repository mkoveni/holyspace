package org.onechurch.communications.domain;
public final class CommunicationRules {
    private CommunicationRules() {
    }
    public static String text(String value, int max) {
        if (value == null || value.isBlank() || value.length() > max) {
            throw CommunicationException.invalid("Enter text of at most " + max + " characters.");
        }
        return value.strip();
    }
    public static void version(long actual, long expected) {
        if (actual != expected) {
            throw CommunicationException.conflict("This record changed. Refresh before saving.");
        }
    }
}
