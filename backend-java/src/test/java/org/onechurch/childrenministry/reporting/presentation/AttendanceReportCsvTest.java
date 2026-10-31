package org.onechurch.childrenministry.reporting.presentation;
import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.childrenministry.reporting.application.AttendanceReport;

import java.time.Instant;
import java.util.List;
import java.util.UUID;
class AttendanceReportCsvTest {
    @Test
    void csvQuotesNamesAndNeutralizesSpreadsheetFormulas() {
        UUID id = UUID.randomUUID();
        AttendanceReport.Visit visit =
            new AttendanceReport.Visit(id, id, id, "  =SUM(1,2)", id, id, "A, \"quoted\" programme", "Class", "Room",
                "Campus", "Africa/Johannesburg", Instant.EPOCH, null, "ATTENDING", false);
        String csv = AttendanceReportCsv.render(new AttendanceReport(List.of(visit), null, "UTC", Instant.EPOCH));
        assertThat(csv).startsWith("\uFEFFChild ID,")
            .contains("\"'  =SUM(1,2)\"", "\"A, \"\"quoted\"\" programme\"")
            .endsWith("\r\n");
    }
}
