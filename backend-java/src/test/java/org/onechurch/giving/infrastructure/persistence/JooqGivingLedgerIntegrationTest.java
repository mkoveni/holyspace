package org.onechurch.giving.infrastructure.persistence;
import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;
import org.onechurch.giving.application.contract.GivingLedgerReader;
import org.onechurch.giving.application.contract.GivingReader;
import org.onechurch.giving.domain.GivingException;
import org.onechurch.shared.infrastructure.GivingIntegrationTestSupport;
import org.springframework.beans.factory.annotation.Autowired;

import java.time.LocalDate;
import java.util.UUID;
class JooqGivingLedgerIntegrationTest extends GivingIntegrationTestSupport {
    @Autowired GivingLedgerReader ledger;
    @Test
    void reportsRejectExcessRowsRatherThanSilentlyTruncating() throws Exception {
        UUID fund = fund("General");
        LocalDate date = LocalDate.of(2026, 1, 1);
        gift(fund, date, 100);
        gift(fund, date, 200);
        GivingReader.Filter filter = new GivingReader.Filter(date, date, null, null, null, 25, 0);
        assertThatThrownBy(() -> ledger.lines(filter, 1)).isInstanceOf(GivingException.class)
            .hasMessageContaining("Narrow");
        assertThat(ledger.lines(filter, 2)).hasSize(2);
    }
}
