package org.onechurch.communications.domain;
import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;

import java.time.Instant;
class PushDeliveryPolicyTest {
    @Test
    void backoffIsBoundedAndRetriesStop() {
        Instant at = Instant.parse("2026-10-29T00:00:00Z");
        assertThat(PushDeliveryPolicy.retryAt(at, 1)).isEqualTo(at.plusSeconds(60));
        assertThat(PushDeliveryPolicy.retryAt(at, 20)).isEqualTo(at.plusSeconds(3600));
        assertThat(PushDeliveryPolicy.retryable(5)).isFalse();
    }
    @Test
    void noticesExpireAtTheExclusiveOneDayBoundary() {
        Instant at = Instant.parse("2026-10-29T00:00:00Z");
        assertThat(PushDeliveryPolicy.expired(at, at.plusSeconds(86399))).isFalse();
        assertThat(PushDeliveryPolicy.expired(at, at.plusSeconds(86400))).isTrue();
    }
}
