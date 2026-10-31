package org.onechurch.people.domain;

import java.time.Instant;
import java.time.LocalDate;
import java.util.Objects;
import java.util.UUID;

public record Person(UUID id, PersonDetails details, MembershipType membershipType, Instant archivedAt, long version,
    Instant createdAt, Instant updatedAt) {

    public Person {
        Objects.requireNonNull(id);
        Objects.requireNonNull(details);
        Objects.requireNonNull(membershipType);
        Objects.requireNonNull(createdAt);
        Objects.requireNonNull(updatedAt);
        if (version < 0 || updatedAt.isBefore(createdAt)) {
            throw PeopleException.invalid("Invalid person revision.");
        }
    }

    public static Person register(UUID id, PersonDetails details, MembershipType membership, LocalDate today,
        Instant now) {
        details.validateAt(today);
        return new Person(id, details, membership == null ? MembershipType.VISITOR : membership, null, 0, now, now);
    }

    public Person update(PersonDetails details, MembershipType membership, long expectedVersion, LocalDate today,
        Instant now) {
        PeopleRules.version(version, expectedVersion);
        details.validateAt(today);
        return new Person(id, details, requireMembership(membership), archivedAt, version + 1, createdAt, now);
    }

    public Person archive(boolean archived, long expectedVersion, Instant now) {
        PeopleRules.version(version, expectedVersion);
        return new Person(id, details, membershipType, archived ? (archivedAt == null ? now : archivedAt) : null,
            version + 1, createdAt, now);
    }

    public Person touch(long expectedVersion, Instant now) {
        PeopleRules.version(version, expectedVersion);
        return new Person(id, details, membershipType, archivedAt, version + 1, createdAt, now);
    }

    private static MembershipType requireMembership(MembershipType membership) {
        if (membership == null) {
            throw PeopleException.invalid("Membership type is required.");
        }
        return membership;
    }

    public void requireAvailable() {
        if (archivedAt != null) {
            throw PeopleException.conflict("Restore the archived person before changing associations.");
        }
    }

    public AgeCategory ageCategory(LocalDate asOf) {
        return AgePolicy.category(details.dateOfBirth(), asOf);
    }
}
