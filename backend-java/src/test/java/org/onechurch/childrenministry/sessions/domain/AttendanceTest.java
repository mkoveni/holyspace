package org.onechurch.childrenministry.sessions.domain;
import static org.assertj.core.api.Assertions.assertThat;
import static org.assertj.core.api.Assertions.assertThatThrownBy;

import org.junit.jupiter.api.Test;
import org.onechurch.childrenministry.shared.domain.ChildrenException;

import java.time.Instant;
import java.util.UUID;
class AttendanceTest {
    private final Instant now = Instant.parse("2026-10-26T08:00:00Z");
    private Attendance arrival() {
        return new Attendance(UUID.randomUUID(), UUID.randomUUID(), UUID.randomUUID(), now, UUID.randomUUID(),
            UUID.randomUUID(), null, null, null, null, null, false, null, null, 0, null, 0, null, 0);
    }
    @Test
    void expiredFailureWindowResetsAndSensitiveHashesNeverAppearInText() {
        Attendance value = arrival().issueLabel("sensitive-hash", now.plusSeconds(3600), 0, "");
        for (int i = 0; i < 5; i++) {
            value = value.failedAttempt(now);
        }
        assertThat(value.throttled(now.plusSeconds(899))).isTrue();
        assertThat(value.throttled(now.plusSeconds(900))).isFalse();
        Attendance reset = value.failedAttempt(now.plusSeconds(900));
        assertThat(reset.failedAttempts()).isEqualTo(1);
        assertThat(value.toString()).doesNotContain("sensitive-hash");
    }
    @Test
    void transferInvalidatesLabelAndRequiresCurrentVersion() {
        Attendance value = arrival().issueLabel("hash", now.plusSeconds(3600), 0, "");
        assertThatThrownBy(() -> value.transfer(UUID.randomUUID(), 0)).isInstanceOf(ChildrenException.class);
        Attendance moved = value.transfer(UUID.randomUUID(), 1);
        assertThat(moved.labelHash()).isNull();
        assertThat(moved.labelVersion()).isEqualTo(2);
    }
}
