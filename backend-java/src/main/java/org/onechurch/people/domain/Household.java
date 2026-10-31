package org.onechurch.people.domain;

import java.time.Instant;
import java.util.Objects;
import java.util.UUID;

public record Household(UUID id, String name, UUID primaryContactMembershipId, long version, Instant createdAt,
    Instant updatedAt) {

    public Household {
        Objects.requireNonNull(id);
        name = PeopleRules.text(name, "Household name", 160);
    }

    public static Household create(UUID id, String name, Instant now) {
        return new Household(id, name, null, 0, now, now);
    }

    public Household update(String name, long expected, Instant now) {
        PeopleRules.version(version, expected);
        return new Household(id, name, primaryContactMembershipId, version + 1, createdAt, now);
    }

    public Household contact(HouseholdMembership member, long expected, Instant now) {
        PeopleRules.version(version, expected);
        if (member != null && (!member.householdId().equals(id) || !member.currentAt(now))) {
            throw PeopleException.invalid("Primary contact must be a current member of this household.");
        }
        return new Household(id, name, member == null ? null : member.id(), version + 1, createdAt, now);
    }

    public Household touch(long expected, Instant now) {
        return update(name, expected, now);
    }
}
