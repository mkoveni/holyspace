package org.onechurch.childrenministry.messaging.domain;
import java.time.Instant;
import java.util.UUID;
public record VerifiedGuardianEmail(UUID id, UUID guardianId, UUID contactId, String email, UUID verifiedBy,
    Instant verifiedAt, Instant validUntil, String evidence, Instant revokedAt) {
    public boolean valid(Instant at) {
        return revokedAt == null && !verifiedAt.isAfter(at) && validUntil.isAfter(at);
    }
    @Override
    public String toString() {
        return "VerifiedGuardianEmail[id=" + id + "]";
    }
}
