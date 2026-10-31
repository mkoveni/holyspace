package org.onechurch.childrenministry.sessions.domain;
import static org.assertj.core.api.Assertions.assertThat;

import org.junit.jupiter.api.Test;
class CapacityPolicyTest {
    @Test
    void leaderMinimumAndRatioApplyTogetherWithPhysicalCapacity() {
        assertThat(CapacityPolicy.capacity(20, 15, 2, true, 2, 6)).isEqualTo(12);
        assertThat(CapacityPolicy.capacity(20, 15, 1, true, 2, 6)).isZero();
        assertThat(CapacityPolicy.capacity(20, 15, 3, false, 2, 6)).isZero();
        assertThat(CapacityPolicy.capacity(20, 15, 3, true, 2, 6)).isEqualTo(15);
        assertThat(CapacityPolicy.capacity(10, 15, 3, true, 2, 6)).isEqualTo(10);
    }
}
