package org.onechurch.childrenministry.sessions.domain;
import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;
import org.onechurch.childrenministry.shared.domain.ChildrenException;

import java.time.LocalDate;
import java.time.LocalTime;
import java.time.ZoneId;
import java.util.Set;
class WeeklyRecurrenceTest {
    @Test
    void weeklyWallTimesRemainStableAcrossDaylightSaving() {
        WeeklyRecurrence rule =
            new WeeklyRecurrence(LocalDate.of(2026, 3, 22), LocalTime.of(9, 0), LocalTime.of(10, 0), 1, 3, Set.of());
        assertThat(rule.slots(ZoneId.of("Europe/London"))).extracting(slot -> slot.startsAt().toString())
            .containsExactly("2026-03-22T09:00:00Z", "2026-03-29T08:00:00Z", "2026-04-05T08:00:00Z");
    }
    @Test
    void ambiguousAndMissingLocalTimesRequireAnExplicitDifferentTime() {
        for (LocalDate date : new LocalDate[]{LocalDate.of(2026, 3, 29), LocalDate.of(2026, 10, 25)}) {
            assertThatThrownBy(() -> new WeeklyRecurrence(date, LocalTime.of(1, 30), LocalTime.of(3, 0), 1, 1, Set.of())
                .slots(ZoneId.of("Europe/London"))).isInstanceOf(ChildrenException.class)
                .hasMessageContaining("ambiguous or unavailable");
        }
    }
    @Test
    void patternsAreBoundedAndOnlyTheirOwnDatesCanBeExcluded() {
        LocalDate first = LocalDate.of(2026, 11, 1);
        WeeklyRecurrence rule =
            new WeeklyRecurrence(first, LocalTime.of(9, 0), LocalTime.of(10, 0), 2, 3, Set.of(first.plusWeeks(2)));
        assertThat(rule.slots(ZoneId.of("Africa/Johannesburg"))).extracting(WeeklyRecurrence.Slot::excluded)
            .containsExactly(false, true, false);
        assertThatThrownBy(() -> new WeeklyRecurrence(first, LocalTime.NOON, LocalTime.of(13, 0), 4, 52, Set.of()))
            .isInstanceOf(ChildrenException.class);
        assertThatThrownBy(
            () -> new WeeklyRecurrence(first, LocalTime.NOON, LocalTime.of(13, 0), 1, 2, Set.of(first.plusDays(1))))
            .isInstanceOf(ChildrenException.class);
        assertThatThrownBy(() -> new WeeklyRecurrence(first, LocalTime.NOON, LocalTime.of(11, 0), 1, 2, Set.of()))
            .isInstanceOf(ChildrenException.class);
        assertThatThrownBy(() -> new WeeklyRecurrence(first, LocalTime.NOON, LocalTime.of(13, 0), 1, 1, Set.of(first)))
            .isInstanceOf(ChildrenException.class);
    }
}
