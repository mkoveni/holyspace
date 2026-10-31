package org.onechurch.giving.presentation;
import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
import org.onechurch.giving.application.contract.GivingLedgerReader;

import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
class GivingCsvTest {
    @Test
    void escapesSpreadsheetFormulasAndKeepsSignedAmountsNumeric() {
        GivingLedgerReader.Line line = new GivingLedgerReader.Line(UUID.randomUUID(), "REVERSAL",
            LocalDate.of(2026, 1, 1), null, "=HYPERLINK(\"unsafe\")", UUID.randomUUID(), "Fund", "ZAR", -12345, "BANK",
            "\t+formula", "Quote \" and newline\n");
        String csv = GivingCsv.render(
            new GivingLedgerReader.Report(null, "", line.date(), line.date(), Instant.now(), List.of(line), List.of()));
        assertThat(csv).contains("'=HYPERLINK", "-12345,", "'\t+formula", "Quote \"\" and newline");
    }
}
