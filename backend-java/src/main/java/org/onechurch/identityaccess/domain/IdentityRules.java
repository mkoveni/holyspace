package org.onechurch.identityaccess.domain;

import java.nio.charset.StandardCharsets;
import java.util.Locale;
import java.util.Map;
import java.util.Objects;
import java.util.Set;
import java.util.stream.Collectors;
import java.util.stream.Stream;

public final class IdentityRules {

    private static final Map<String, String> ADDITIONAL_PERMISSIONS = Map.of("volunteering.manage",
        "volunteering.serve", "ministries.manage", "ministries.lead", "followups.manage", "followups.work");

    private IdentityRules() {
    }

    public static String username(String value) {
        String normalized = normalize(value);
        if (!normalized.matches("[a-z0-9._-]{3,80}")) {
            invalid("Username must contain 3–80 letters, digits, dots, underscores or hyphens.");
        }
        return normalized;
    }

    public static String email(String value) {
        String normalized = normalize(value);
        if (normalized.length() > 254 || !normalized.matches("[^\\s@]+@[^\\s@]+\\.[^\\s@]+")) {
            invalid("A valid email address is required.");
        }
        return normalized;
    }

    public static String name(String value) {
        String name = value == null ? "" : value.strip();
        if (name.isBlank() || name.length() > 160) {
            invalid("Display name is required and must be at most 160 characters.");
        }
        return name;
    }

    public static void password(String value) {
        if (value == null || value.length() < 12 || value.getBytes(StandardCharsets.UTF_8).length > 72
            || value.isBlank()) {
            invalid("Password must contain at least 12 characters and at most 72 UTF-8 bytes.");
        }
    }

    public static String normalize(String value) {
        return value == null ? "" : value.strip().toLowerCase(Locale.ROOT);
    }

    public static Set<String> effectivePermissions(Set<String> granted) {
        Stream<String> readPermissions = granted.stream()
            .filter(code -> code.endsWith(".manage"))
            .map(code -> code.substring(0, code.length() - 7) + ".read");
        Stream<String> additionalPermissions =
            granted.stream().map(ADDITIONAL_PERMISSIONS::get).filter(Objects::nonNull);
        return Stream.concat(Stream.concat(granted.stream(), readPermissions), additionalPermissions)
            .collect(Collectors.toUnmodifiableSet());
    }

    private static void invalid(String message) {
        throw new IdentityException(IdentityException.Kind.INVALID, message);
    }
}
