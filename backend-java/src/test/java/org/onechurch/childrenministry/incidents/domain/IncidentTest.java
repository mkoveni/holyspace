package org.onechurch.childrenministry.incidents.domain;
import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;
import org.onechurch.childrenministry.shared.domain.ChildrenException;

import java.time.Instant;
import java.time.LocalDate;
import java.util.UUID;
class IncidentTest {
    private Incident report() {
        Instant now = Instant.parse("2026-10-26T10:00:00Z");
        return new Incident(UUID.randomUUID(), UUID.randomUUID(), UUID.randomUUID(), UUID.randomUUID(), "SAFEGUARDING",
            "HIGH", now, UUID.randomUUID(), now, "OPEN", null, LocalDate.of(2026, 10, 26), LocalDate.of(2027, 10, 26),
            0);
    }
    @Test
    void closureNeedsAnOwnerAndReopeningPreservesIdentity() {
        Incident open = report();
        LocalDate on = LocalDate.of(2026, 10, 26);
        assertThatThrownBy(() -> open.progress(0, "CLOSED", on)).isInstanceOf(ChildrenException.class);
        Incident triaged = open.triage(0, UUID.randomUUID(), on.plusDays(1), on);
        Incident closed = triaged.progress(1, "CLOSED", on);
        assertThatThrownBy(() -> closed.progress(2, "ACTION", on)).isInstanceOf(ChildrenException.class);
        Incident reopened = closed.progress(2, "REOPENED", on);
        assertThat(reopened.id()).isEqualTo(open.id());
        assertThat(reopened.status()).isEqualTo("IN_REVIEW");
        assertThat(reopened.version()).isEqualTo(3);
    }
    @Test
    void reviewCannotExtendPastRetentionAndStaleVersionsCannotChangeResponsibility() {
        Incident incident = report();
        LocalDate on = LocalDate.of(2026, 10, 26);
        assertThatThrownBy(() -> incident.triage(0, UUID.randomUUID(), on.plusYears(2), on))
            .isInstanceOf(ChildrenException.class);
        assertThatThrownBy(() -> incident.triage(1, UUID.randomUUID(), on, on)).isInstanceOf(ChildrenException.class);
    }
    @Test
    void privatePayloadRepresentationsNeverIncludeTheirContent() {
        assertThat(new IncidentDetails("Sensitive report", "Sensitive response").toString())
            .doesNotContain("Sensitive");
        assertThat(new IncidentEvent(UUID.randomUUID(), UUID.randomUUID(), 0, "ACTION", UUID.randomUUID(),
            Instant.now(), "Sensitive conversation", null).toString()).doesNotContain("Sensitive");
    }
}
