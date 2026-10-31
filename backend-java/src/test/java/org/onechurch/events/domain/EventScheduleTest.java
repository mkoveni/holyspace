package org.onechurch.events.domain;
import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;

import java.time.LocalDate;
import java.time.LocalTime;
import java.util.UUID;
class EventScheduleTest {
    @Test
    void rejectsMissingAndAmbiguousDstTimes() {
        assertThatThrownBy(() -> EventSchedule.window(LocalDate.of(2027, 3, 14), LocalTime.of(2, 30),
            LocalTime.of(4, 0), 0, "America/New_York")).isInstanceOf(EventException.class)
            .hasMessageContaining("daylight");
        assertThatThrownBy(() -> EventSchedule.window(LocalDate.of(2027, 11, 7), LocalTime.of(1, 30),
            LocalTime.of(3, 0), 0, "America/New_York")).isInstanceOf(EventException.class)
            .hasMessageContaining("ambiguous");
    }
    @Test
    void weeklySeriesRetainsLocalTimesAcrossDst() {
        EventSeries series = new EventSeries(UUID.randomUUID(), UUID.randomUUID(), "America/New_York", "WEEKLY", 1, 2,
            LocalDate.of(2027, 3, 7), LocalTime.of(9, 0), LocalTime.of(10, 0), 0, UUID.randomUUID(), 0);
        assertThat(EventSchedule.preview(series).stream().map(w -> w.startsAt().toString()))
            .containsExactly("2027-03-07T14:00:00Z", "2027-03-14T13:00:00Z");
    }
    @Test
    void monthlySeriesRejectsClampedDatesAndUnboundedRecurrence() {
        EventSeries series = new EventSeries(UUID.randomUUID(), UUID.randomUUID(), "Africa/Johannesburg", "MONTHLY", 1,
            2, LocalDate.of(2027, 1, 31), LocalTime.of(9, 0), LocalTime.of(10, 0), 0, UUID.randomUUID(), 0);
        assertThatThrownBy(() -> EventSchedule.preview(series)).isInstanceOf(EventException.class)
            .hasMessageContaining("monthly date");
        EventSeries longSeries = new EventSeries(UUID.randomUUID(), UUID.randomUUID(), "Africa/Johannesburg", "WEEKLY",
            52, 4, LocalDate.of(2027, 1, 4), LocalTime.of(9, 0), LocalTime.of(10, 0), 0, UUID.randomUUID(), 0);
        assertThatThrownBy(() -> EventSchedule.preview(longSeries)).isInstanceOf(EventException.class)
            .hasMessageContaining("two years");
    }
}
