package org.onechurch.giving.domain;
import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;

import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
class GiftTest {
    private final UUID fund = UUID.randomUUID();
    private Gift gift(List<Gift.Allocation> allocations) {
        return new Gift(UUID.randomUUID(), null, new Money(10000, "ZAR"), LocalDate.of(2026, 1, 1), "BANK", null,
            allocations, UUID.randomUUID(), Instant.now());
    }
    @Test
    void requiresExactAllocationBalance() {
        assertThatThrownBy(() -> gift(List.of(new Gift.Allocation(fund, 9999)))).isInstanceOf(GivingException.class);
    }
    @Test
    void rejectsDuplicateFundsAndNonPositiveAllocations() {
        assertThatThrownBy(() -> gift(List.of(new Gift.Allocation(fund, 5000), new Gift.Allocation(fund, 5000))))
            .isInstanceOf(GivingException.class);
        assertThatThrownBy(() -> gift(List.of(new Gift.Allocation(fund, 0)))).isInstanceOf(GivingException.class);
    }
    @Test
    void amountsAndCurrenciesAreBounded() {
        assertThatThrownBy(() -> new Money(0, "ZAR")).isInstanceOf(GivingException.class);
        assertThatThrownBy(() -> new Money(1000000000001L, "ZAR")).isInstanceOf(GivingException.class);
        assertThatThrownBy(() -> new Money(100, "JPY")).isInstanceOf(GivingException.class);
    }
    @Test
    void reversalRetainsOriginalAndRequiresChronologyAndReason() {
        Gift original = gift(List.of(new Gift.Allocation(fund, 10000)));
        assertThatThrownBy(() -> original.reverse(UUID.randomUUID(), LocalDate.of(2025, 12, 31), "Correction",
            UUID.randomUUID(), Instant.now())).isInstanceOf(GivingException.class);
        assertThatThrownBy(
            () -> original.reverse(UUID.randomUUID(), LocalDate.of(2026, 1, 2), " ", UUID.randomUUID(), Instant.now()))
            .isInstanceOf(GivingException.class);
        assertThat(
            original
                .reverse(UUID.randomUUID(), LocalDate.of(2026, 1, 2), "Duplicate recorded", UUID.randomUUID(),
                    Instant.now())
                .giftId())
            .isEqualTo(original.id());
        assertThat(original.money().amountMinor()).isEqualTo(10000);
    }
}
