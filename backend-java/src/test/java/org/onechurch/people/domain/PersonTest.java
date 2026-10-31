package org.onechurch.people.domain;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;

import java.time.Instant;
import java.time.LocalDate;
import java.util.UUID;

class PersonTest {

    private final Instant now = Instant.parse("2026-10-25T10:00:00Z");
    private final LocalDate today = LocalDate.of(2026, 10, 25);

    private PersonDetails details(LocalDate birth) {
        return new PersonDetails("dr", " Solly ", "Mabasa", birth, null, null, null);
    }

    @Test
    void normalizesTitleAndNameAndDefaultsOnlyToVisitor() {
        Person person = Person.register(UUID.randomUUID(), details(null), null, today, now);
        assertThat(person.details().titleCode()).isEqualTo("DR");
        assertThat(person.details().firstName()).isEqualTo("Solly");
        assertThat(person.membershipType()).isEqualTo(MembershipType.VISITOR);
        assertThat(MembershipType.values()).extracting(MembershipType::label)
            .containsExactly("Active Member", "Regular Visitor", "Visitor", "Transferred");
    }

    @Test
    void derivesAgeAtTheOccurrenceDateWithoutChangingIdentityOrMembership() {
        Person person = Person.register(UUID.randomUUID(), details(LocalDate.of(2008, 10, 26)),
            MembershipType.ACTIVE_MEMBER, today, now);
        assertThat(person.ageCategory(today)).isEqualTo(AgeCategory.CHILD);
        assertThat(person.ageCategory(today.plusDays(1))).isEqualTo(AgeCategory.ADULT);
        assertThat(person.ageCategory(LocalDate.of(2008, 10, 25))).isEqualTo(AgeCategory.UNKNOWN);
        assertThat(person.ageCategory(LocalDate.of(2027, 1, 1))).isEqualTo(AgeCategory.ADULT);
        assertThat(person.membershipType()).isEqualTo(MembershipType.ACTIVE_MEMBER);
        assertThat(Person.register(UUID.randomUUID(), details(null), null, today, now).ageCategory(today))
            .isEqualTo(AgeCategory.UNKNOWN);
    }

    @Test
    void rejectsFutureDatesAndInconsistentDeceasedDetails() {
        assertThatThrownBy(() -> Person.register(UUID.randomUUID(), details(today.plusDays(1)), null, today, now))
            .isInstanceOf(PeopleException.class);
        assertThatThrownBy(() -> new PersonDetails(null, "A", "B", today, null, LifeStatus.LIVING, today))
            .isInstanceOf(PeopleException.class);
        assertThatThrownBy(
            () -> new PersonDetails(null, "A", "B", today, null, LifeStatus.DECEASED, today.minusDays(1)))
            .isInstanceOf(PeopleException.class);
    }

    @Test
    void archivalIsReversibleAndStaleWritesFail() {
        Person person = Person.register(UUID.randomUUID(), details(null), MembershipType.TRANSFERRED, today, now);
        Person archived = person.archive(true, 0, now.plusSeconds(1));
        assertThat(archived.membershipType()).isEqualTo(MembershipType.TRANSFERRED);
        assertThatThrownBy(archived::requireAvailable).isInstanceOf(PeopleException.class);
        assertThatThrownBy(() -> archived.archive(false, 0, now.plusSeconds(2))).isInstanceOf(PeopleException.class);
        Person restored = archived.archive(false, 1, now.plusSeconds(2));
        assertThat(restored.id()).isEqualTo(person.id());
        assertThat(restored.archivedAt()).isNull();
    }
}
