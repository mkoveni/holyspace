package org.onechurch.communications.infrastructure.persistence;
import java.time.Instant;
import java.time.OffsetDateTime;
import java.time.ZoneOffset;
final class CommunicationRecords {
    private CommunicationRecords() {
    }
    static Instant instant(OffsetDateTime value) {
        return value == null ? null : value.toInstant();
    }
    static OffsetDateTime time(Instant value) {
        return value == null ? null : value.atOffset(ZoneOffset.UTC);
    }
}
