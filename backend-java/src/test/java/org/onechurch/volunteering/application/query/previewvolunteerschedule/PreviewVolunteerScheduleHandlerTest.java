package org.onechurch.volunteering.application.query.previewvolunteerschedule;

import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;
import org.onechurch.volunteering.application.RosterPlanning;
import org.onechurch.volunteering.application.ScheduleDates;
import org.onechurch.volunteering.domain.VolunteerException;

import java.time.LocalDate;
import java.time.LocalTime;
import java.util.List;
import java.util.UUID;

class PreviewVolunteerScheduleHandlerTest {
    @Test
    void previewsAreDeterministicOrderedAndExplicitAboutOvernightDuty() {
        UUID request = UUID.randomUUID(), team = UUID.randomUUID(), role = UUID.randomUUID();
        List<LocalDate> dates = List.of(LocalDate.of(2026, 11, 8), LocalDate.of(2026, 11, 1));
        List<RosterPlanning.Draft> first = ScheduleDates.preview(request, team, role, dates, LocalTime.of(22, 0),
            LocalTime.of(2, 0), "Africa/Johannesburg", 2);
        assertThat(first).isEqualTo(ScheduleDates.preview(request, team, role, dates, LocalTime.of(22, 0),
            LocalTime.of(2, 0), "Africa/Johannesburg", 2));
        assertThat(first.getFirst().endsAt()).isEqualTo(first.getFirst().startsAt().plusSeconds(14400));
    }
    @Test
    void repeatedDatesAndAmbiguousLocalTimesAreRejected() {
        UUID id = UUID.randomUUID();
        LocalDate day = LocalDate.of(2026, 11, 1);
        assertThatThrownBy(() -> ScheduleDates.preview(id, id, id, List.of(day, day), LocalTime.of(8, 0),
            LocalTime.of(9, 0), "UTC", 1)).isInstanceOf(VolunteerException.class);
        assertThatThrownBy(() -> ScheduleDates.preview(id, id, id, List.of(day), LocalTime.of(1, 30),
            LocalTime.of(3, 0), "America/New_York", 1)).isInstanceOf(VolunteerException.class);
    }
}
