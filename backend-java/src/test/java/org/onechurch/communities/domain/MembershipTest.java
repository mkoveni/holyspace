package org.onechurch.communities.domain;
import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;

import java.time.Instant;
import java.util.UUID;
class MembershipTest {
    @Test
    void membershipPeriodsAreHalfOpenAndEndingDoesNotRewriteTheStart() {
        Instant from = Instant.parse("2026-10-27T10:00:00Z"), until = from.plusSeconds(3600);
        Membership m = new Membership(UUID.randomUUID(), UUID.randomUUID(), UUID.randomUUID(), from, null, 0),
            ended = m.end(until);
        assertThat(ended.covers(from)).isTrue();
        assertThat(ended.covers(until)).isFalse();
        assertThat(ended.startedAt()).isEqualTo(from);
        assertThat(ended.version()).isEqualTo(1);
        assertThatThrownBy(() -> ended.end(until)).isInstanceOf(CommunityException.class);
    }
    @Test
    void ministriesCannotHaveAParentAndNamesAreRequired() {
        assertThatThrownBy(
            () -> new Community(UUID.randomUUID(), "MINISTRY", "Name", "", UUID.randomUUID(), "", true, 0))
            .isInstanceOf(CommunityException.class);
        assertThatThrownBy(() -> new Community(UUID.randomUUID(), "GROUP", " ", "", null, "", true, 0))
            .isInstanceOf(CommunityException.class);
    }
}
