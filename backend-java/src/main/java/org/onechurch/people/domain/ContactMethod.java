package org.onechurch.people.domain;

import java.time.Instant;
import java.util.Locale;
import java.util.UUID;

public record ContactMethod(UUID id, UUID personId, Kind kind, String value, boolean primary, Instant createdAt,
    Instant endedAt) {

    public enum Kind {
        EMAIL, PHONE
    }

    public ContactMethod {
        if (kind == null) {
            throw PeopleException.invalid("Contact kind is required.");
        }
        value = PeopleRules.text(value, "Contact value", 254);
        if (kind == Kind.EMAIL) {
            value = value.toLowerCase(Locale.ROOT);
            if (!value.matches("[^\\s@]+@[^\\s@]+\\.[^\\s@]+")) {
                throw PeopleException.invalid("Invalid email address.");
            }
        } else if (!value.matches("[+0-9() .-]{3,40}")) {
            throw PeopleException.invalid("Invalid phone number.");
        }
    }
}
