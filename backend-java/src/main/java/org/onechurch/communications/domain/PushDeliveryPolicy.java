package org.onechurch.communications.domain;
import java.time.Duration;
import java.time.Instant;
public final class PushDeliveryPolicy {
    private PushDeliveryPolicy() {
    }
    public static Instant retryAt(Instant at, int attempts) {
        return at.plusSeconds(Math.min(3600, 30L << Math.min(attempts, 7)));
    }
    public static boolean expired(Instant created, Instant at) {
        return !created.plus(Duration.ofHours(24)).isAfter(at);
    }
    public static boolean retryable(int attempts) {
        return attempts < 5;
    }
}
