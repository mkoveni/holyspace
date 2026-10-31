package org.onechurch.childrenministry.reporting.application;
import static org.assertj.core.api.Assertions.assertThatThrownBy;
import static org.mockito.ArgumentMatchers.any;
import static org.mockito.ArgumentMatchers.eq;
import static org.mockito.Mockito.mock;
import static org.mockito.Mockito.verifyNoInteractions;
import static org.mockito.Mockito.when;

import org.junit.jupiter.api.Test;
import org.onechurch.childrenministry.reporting.application.contract.AttendanceReportReader;
import org.onechurch.childrenministry.shared.domain.ChildrenException;
import org.onechurch.people.application.contract.PersonDirectory;

import java.time.Clock;
import java.time.LocalDate;
import java.time.ZoneId;
import java.util.Collections;
class AttendanceReportingTest {
    @Test
    void oversizedExportsAreRejectedBeforeReturningPartialResultsOrLoadingNames() {
        AttendanceReportReader reader = mock(AttendanceReportReader.class);
        PersonDirectory people = mock(PersonDirectory.class);
        when(reader.read(any(), eq(10001), eq(0)))
            .thenReturn(new AttendanceReport(Collections.nCopies(10001, null), null, "", null));
        AttendanceReporting reporting =
            new AttendanceReporting(reader, people, ZoneId.of("Africa/Johannesburg"), Clock.systemUTC());
        LocalDate day = LocalDate.of(2026, 10, 26);
        assertThatThrownBy(() -> reporting.export(new AttendanceReportFilter(day, day, null, null, null, "", "ALL")))
            .isInstanceOf(ChildrenException.class)
            .hasMessageContaining("10,000");
        verifyNoInteractions(people);
    }
}
