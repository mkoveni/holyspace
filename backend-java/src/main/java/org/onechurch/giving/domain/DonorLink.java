package org.onechurch.giving.domain;
import java.time.Instant;
import java.util.UUID;
public record DonorLink(UUID accountId, UUID personId, boolean active, long version, UUID verifiedBy,
    Instant verifiedAt) {
    public DonorLink {
        if (accountId == null || personId == null || verifiedBy == null || verifiedAt == null || version < 1) {
            throw GivingException.invalid("A donor link requires account, person and verification evidence.");
        }
    }
    public DonorLink revise(UUID person, boolean enabled, long expected, UUID actor, Instant now) {
        GivingRules.version(version, expected);
        if (!personId.equals(person)) {
            throw GivingException.conflict(
                "A verified account cannot be reassigned to another donor. Revoke this link and contact support to correct identity evidence.");
        }
        return new DonorLink(accountId, personId, enabled, version + 1, actor, now);
    }
}
