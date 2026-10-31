package org.onechurch.giving.domain;
import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;

import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
class PledgeTest {
    private final UUID donor = UUID.randomUUID(), fund = UUID.randomUUID(), actor = UUID.randomUUID();
    private Pledge pledge() {
        return new Pledge(UUID.randomUUID(), donor, fund, new Money(10000, "ZAR"), LocalDate.of(2026, 1, 1),
            LocalDate.of(2026, 12, 31), "OPEN", 0, null, actor, Instant.now());
    }
    private Gift gift(String currency, LocalDate date) {
        return new Gift(UUID.randomUUID(), donor, new Money(10000, currency), date, "BANK", null,
            List.of(new Gift.Allocation(fund, 10000)), actor, Instant.now());
    }
    @Test
    void periodCurrencyAndAvailableBudgetsAreIndependentInvariants() {
        Pledge pledge = pledge();
        assertThatThrownBy(() -> pledge.validateAssignment(gift("USD", LocalDate.of(2026, 2, 1)), 100, 0, 10000))
            .isInstanceOf(GivingException.class);
        assertThatThrownBy(() -> pledge.validateAssignment(gift("ZAR", LocalDate.of(2025, 2, 1)), 100, 0, 10000))
            .isInstanceOf(GivingException.class);
        assertThatThrownBy(() -> pledge.validateAssignment(gift("ZAR", LocalDate.of(2026, 2, 1)), 5000, 6000, 10000))
            .isInstanceOf(GivingException.class);
        assertThatThrownBy(() -> pledge.validateAssignment(gift("ZAR", LocalDate.of(2026, 2, 1)), 5000, 0, 4000))
            .isInstanceOf(GivingException.class);
    }
    @Test
    void cancellingPreservesTheCommitmentAndRequiresItsCurrentVersion() {
        Pledge original = pledge();
        Pledge cancelled = original.cancel(0, "Donor withdrew commitment");
        assertThat(cancelled.target()).isEqualTo(original.target());
        assertThat(cancelled.status()).isEqualTo("CANCELLED");
        assertThatThrownBy(() -> original.cancel(1, "Stale screen")).isInstanceOf(GivingException.class);
        assertThatThrownBy(() -> cancelled.requireOpen()).isInstanceOf(GivingException.class);
    }
    @Test
    void assignmentCancellationKeepsItsOwnActorTimestampAndOriginalEvidence() {
        Instant at = Instant.now();
        PledgeAssignment assignment = new PledgeAssignment(UUID.randomUUID(), UUID.randomUUID(), UUID.randomUUID(),
            fund, donor, "ZAR", 100, "ACTIVE", 0, null, null, null, actor, at);
        UUID cancellingActor = UUID.randomUUID();
        PledgeAssignment cancelled = assignment.cancel(0, "Wrong pledge", cancellingActor, at.plusSeconds(60));
        assertThat(cancelled.createdBy()).isEqualTo(actor);
        assertThat(cancelled.createdAt()).isEqualTo(at);
        assertThat(cancelled.cancelledBy()).isEqualTo(cancellingActor);
        assertThat(cancelled.cancelledAt()).isEqualTo(at.plusSeconds(60));
    }
}
