package org.onechurch.volunteering.application;
import static org.assertj.core.api.Assertions.assertThat;
import static org.mockito.Mockito.mock;
import static org.mockito.Mockito.when;

import org.junit.jupiter.api.Test;
import org.onechurch.volunteering.application.contract.ActivityStaffing;
import org.onechurch.volunteering.application.contract.RosterReader;
import org.onechurch.volunteering.domain.Assignment;
import org.onechurch.volunteering.domain.Shift;

import java.time.Instant;
import java.util.List;
import java.util.UUID;
class ActivityStaffingSummaryTest {
    private final RosterReader roster = mock(RosterReader.class);
    private final VolunteerReadiness readiness = mock(VolunteerReadiness.class);
    private final UUID source = UUID.randomUUID();
    private final Instant now = Instant.parse("2026-10-27T10:00:00Z");
    private Shift shift(int positions) {
        return new Shift(UUID.randomUUID(), UUID.randomUUID(), "hash", UUID.randomUUID(), UUID.randomUUID(),
            "EVENT_OCCURRENCE", source, "0", now, now.plusSeconds(3600), positions, "OPEN", 0, 0);
    }
    private Assignment assignment(Shift shift, String status) {
        return new Assignment(UUID.randomUUID(), UUID.randomUUID(), "hash", shift.id(), UUID.randomUUID(), status, 0,
            UUID.randomUUID(), now);
    }
    @Test
    void confirmedButUnreadyAndProvisionalAssignmentsNeverFillReadinessGaps() {
        Shift first = shift(1), second = shift(1);
        Assignment ready = assignment(first, "CONFIRMED"), unready = assignment(second, "CONFIRMED"),
            proposed = assignment(second, "PROPOSED");
        when(roster.sourceShifts("EVENT_OCCURRENCE", source, 201)).thenReturn(List.of(first, second));
        when(roster.assignments(first.id())).thenReturn(List.of(ready));
        when(roster.assignments(second.id())).thenReturn(List.of(unready, proposed));
        when(readiness.assess(ready.volunteerId(), first, ready.id()))
            .thenReturn(new VolunteerReadiness.Assessment(true, List.of()));
        when(readiness.assess(unready.volunteerId(), second, unready.id()))
            .thenReturn(new VolunteerReadiness.Assessment(false, List.of("Screening expired")));
        ActivityStaffing.Summary summary =
            new ActivityStaffingSummary(roster, readiness).summarize("EVENT_OCCURRENCE", source);
        assertThat(summary.confirmed()).isEqualTo(2);
        assertThat(summary.ready()).isEqualTo(1);
        assertThat(summary.proposed()).isEqualTo(1);
        assertThat(summary.gaps()).isEqualTo(1);
    }
    @Test
    void extraCoverageInOneDutyDoesNotHideGapsInAnother() {
        Shift first = shift(1), second = shift(1);
        Assignment one = assignment(first, "CONFIRMED"), two = assignment(first, "CONFIRMED");
        when(roster.sourceShifts("EVENT_OCCURRENCE", source, 201)).thenReturn(List.of(first, second));
        when(roster.assignments(first.id())).thenReturn(List.of(one, two));
        when(roster.assignments(second.id())).thenReturn(List.of());
        when(readiness.assess(one.volunteerId(), first, one.id()))
            .thenReturn(new VolunteerReadiness.Assessment(true, List.of()));
        when(readiness.assess(two.volunteerId(), first, two.id()))
            .thenReturn(new VolunteerReadiness.Assessment(true, List.of()));
        assertThat(new ActivityStaffingSummary(roster, readiness).summarize("EVENT_OCCURRENCE", source).gaps())
            .isEqualTo(1);
    }
}
