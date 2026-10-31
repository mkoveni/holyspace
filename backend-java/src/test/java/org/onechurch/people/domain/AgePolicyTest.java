package org.onechurch.people.domain;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;

import java.time.LocalDate;

class AgePolicyTest {
    @Test
    void usesFebruary28ForLeapDayBirthdaysInNonLeapYears() {
        LocalDate birth = LocalDate.of(2008, 2, 29);
        assertThat(AgePolicy.category(birth, LocalDate.of(2026, 2, 27))).isEqualTo(AgeCategory.CHILD);
        assertThat(AgePolicy.category(birth, LocalDate.of(2026, 2, 28))).isEqualTo(AgeCategory.ADULT);
        assertThat(AgePolicy.category(birth, LocalDate.of(2026, 3, 1))).isEqualTo(AgeCategory.ADULT);
        assertThat(AgePolicy.category(LocalDate.of(2006, 3, 1), LocalDate.of(2024, 2, 29)))
            .isEqualTo(AgeCategory.CHILD);
    }
}
