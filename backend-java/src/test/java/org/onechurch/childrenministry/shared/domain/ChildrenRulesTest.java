package org.onechurch.childrenministry.shared.domain;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;
import org.onechurch.childrenministry.participation.domain.MinistryClass;

import java.time.LocalDate;
class ChildrenRulesTest {
    @Test
    void unknownAgeIsNeverAdultOrChild() {
        LocalDate date = LocalDate.of(2026, 10, 26);
        assertThatThrownBy(() -> ChildrenRules.adult(null, date)).isInstanceOf(ChildrenException.class);
        assertThatThrownBy(() -> ChildrenRules.child(null, date)).isInstanceOf(ChildrenException.class);
    }
    @Test
    void eighteenthBirthdayEndsChildEligibilityIncludingLeapBirthdays() {
        LocalDate birthday = LocalDate.of(2008, 2, 29);
        ChildrenRules.child(birthday, LocalDate.of(2026, 2, 27));
        ChildrenRules.adult(birthday, LocalDate.of(2026, 2, 28));
        assertThatThrownBy(() -> ChildrenRules.child(birthday, LocalDate.of(2026, 2, 28)))
            .isInstanceOf(ChildrenException.class);
    }
    @Test
    void classAgeBoundsAreInclusiveThenExclusive() {
        MinistryClass group =
            new MinistryClass(java.util.UUID.randomUUID(), java.util.UUID.randomUUID(), "Juniors", 60, 120);
        LocalDate birthday = LocalDate.of(2016, 10, 26);
        group.requireAge(birthday, LocalDate.of(2021, 10, 26));
        group.requireAge(birthday, LocalDate.of(2026, 10, 25));
        assertThatThrownBy(() -> group.requireAge(birthday, LocalDate.of(2026, 10, 26)))
            .isInstanceOf(ChildrenException.class);
    }
}
