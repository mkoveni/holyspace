package org.onechurch.people.domain;

import java.util.Locale;

public final class PeopleRules {

    private PeopleRules() {
    }

    public static String text(String value, String name, int max) {
        if (value == null || value.isBlank() || value.strip().length() > max) {
            throw PeopleException.invalid(name + " is required and must be at most " + max + " characters.");
        }
        return value.strip();
    }

    public static String optionalText(String value, String name, int max) {
        return value == null || value.isBlank() ? "" : text(value, name, max);
    }

    public static String titleCode(String value) {
        if (value == null || value.isBlank()) {
            return null;
        }
        String code = text(value, "Title code", 20).toUpperCase(Locale.ROOT);
        if (!code.matches("[A-Z][A-Z0-9_]{0,19}")) {
            throw PeopleException.invalid("Invalid title code.");
        }
        return code;
    }

    public static void version(long actual, long expected) {
        if (expected < 0 || actual != expected) {
            throw PeopleException.conflict("The record has changed. Reload and try again.");
        }
    }
}
