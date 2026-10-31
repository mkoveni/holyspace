package org.onechurch.volunteering.domain;

import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;

import java.time.Instant;
import java.time.LocalDate;
import java.time.LocalTime;
import java.util.List;
import java.util.UUID;

class AvailabilityTest {
    @Test
    void unknownIsNotAvailableAndAdjacentWindowsMergeButUnavailableExceptionsWin() {
        Instant from = Instant.parse("2026-10-25T08:00:00Z"), until = from.plusSeconds(7200);
        UUID person = UUID.randomUUID();
        assertThat(Availability.covers(from, until, List.of(), List.of())).isFalse();
        List<AvailabilityException> windows =
            List.of(new AvailabilityException(UUID.randomUUID(), person, 0, from, from.plusSeconds(3600), true),
                new AvailabilityException(UUID.randomUUID(), person, 0, from.plusSeconds(3600), until, true));
        assertThat(Availability.covers(from, until, List.of(), windows)).isTrue();
        assertThat(Availability.covers(from, until, List.of(),
            List.of(windows.getFirst(), windows.getLast(), new AvailabilityException(UUID.randomUUID(), person, 0,
                from.plusSeconds(30), from.plusSeconds(40), false))))
            .isFalse();
    }
    @Test
    void overnightRulesUseTheRuleTimeZoneAndHalfOpenEffectiveDates() {
        AvailabilityRule rule = new AvailabilityRule(UUID.randomUUID(), UUID.randomUUID(), 0, 7, LocalTime.of(22, 0),
            LocalTime.of(2, 0), "Africa/Johannesburg", LocalDate.of(2026, 10, 25), LocalDate.of(2026, 10, 26));
        assertThat(Availability.covers(Instant.parse("2026-10-25T21:00:00Z"), Instant.parse("2026-10-26T00:00:00Z"),
            List.of(rule), List.of())).isTrue();
        assertThat(Availability.covers(Instant.parse("2026-11-01T21:00:00Z"), Instant.parse("2026-11-02T00:00:00Z"),
            List.of(rule), List.of())).isFalse();
    }
    @Test
    void ambiguousDaylightSavingWindowsDoNotSilentlyGrantAvailability() {
        AvailabilityRule rule = new AvailabilityRule(UUID.randomUUID(), UUID.randomUUID(), 0, 7, LocalTime.of(1, 30),
            LocalTime.of(3, 0), "America/New_York", LocalDate.of(2026, 1, 1), LocalDate.of(2027, 1, 1));
        assertThat(Availability.covers(Instant.parse("2026-11-01T05:30:00Z"), Instant.parse("2026-11-01T07:30:00Z"),
            List.of(rule), List.of())).isFalse();
    }
}
