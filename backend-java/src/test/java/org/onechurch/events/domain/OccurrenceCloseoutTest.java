package org.onechurch.events.domain;
import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;

import java.time.Instant;
import java.time.LocalDate;
import java.util.UUID;
class OccurrenceCloseoutTest {
    private final Instant end = Instant.parse("2026-10-27T10:00:00Z");
    private final UUID actor = UUID.randomUUID();
    private final EventOccurrence occurrence =
        new EventOccurrence(UUID.randomUUID(), UUID.randomUUID(), UUID.randomUUID(), LocalDate.of(2026, 10, 27), "UTC",
            end.minusSeconds(3600), end, UUID.randomUUID(), "Main hall", "", false, "SCHEDULED", false, 0, 0);
    @Test
    void closeoutStartsAtTheExactEndBoundaryAndRequiresBothReviews() {
        OccurrenceCloseout current = OccurrenceCloseout.open(occurrence.id());
        assertThatThrownBy(
            () -> current.close(occurrence, 0, "hash", "Reviewed", "", true, true, false, actor, end.minusNanos(1)))
            .isInstanceOf(EventException.class);
        assertThatThrownBy(() -> current.close(occurrence, 0, "hash", "Reviewed", "", true, false, false, actor, end))
            .isInstanceOf(EventException.class);
        assertThat(current.close(occurrence, 0, "hash", "Reviewed", "", true, true, false, actor, end).status())
            .isEqualTo("CLOSED");
    }
    @Test
    void unresolvedWorkNeedsFollowUpAndReopeningRetainsASeparateRevision() {
        OccurrenceCloseout current = OccurrenceCloseout.open(occurrence.id());
        assertThatThrownBy(() -> current.close(occurrence, 0, "hash", "Reviewed", " ", true, true, true, actor, end))
            .isInstanceOf(EventException.class);
        OccurrenceCloseout closed =
            current.close(occurrence, 0, "hash", "Reviewed", "Phone family tomorrow", true, true, true, actor, end);
        OccurrenceCloseout reopened = closed.reopen(1, "Attendance correction", actor, end.plusSeconds(10));
        assertThat(reopened.version()).isEqualTo(2);
        assertThat(reopened.status()).isEqualTo("OPEN");
        assertThat(closed.status()).isEqualTo("CLOSED");
        assertThatThrownBy(() -> closed.reopen(0, "Stale", actor, end)).isInstanceOf(EventException.class);
    }
}
