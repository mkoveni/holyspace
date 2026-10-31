package org.onechurch.people.domain;

import java.time.Instant;
import java.util.Locale;
import java.util.UUID;

public record PersonAddress(UUID id, UUID personId, String line1, String line2, String city, String region,
    String postalCode, String countryCode, Instant createdAt, Instant endedAt) {

    public PersonAddress {
        line1 = PeopleRules.text(line1, "Address line", 200);
        line2 = PeopleRules.optionalText(line2, "Address line 2", 200);
        city = PeopleRules.text(city, "City", 100);
        region = PeopleRules.optionalText(region, "Region", 100);
        postalCode = PeopleRules.optionalText(postalCode, "Postal code", 20);
        countryCode = PeopleRules.text(countryCode, "Country code", 2).toUpperCase(Locale.ROOT);
        if (!countryCode.matches("[A-Z]{2}")) {
            throw PeopleException.invalid("Use a two-letter country code.");
        }
    }
}
