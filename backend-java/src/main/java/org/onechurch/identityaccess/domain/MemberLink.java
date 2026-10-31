package org.onechurch.identityaccess.domain;
import java.time.Instant;
import java.util.UUID;
public record MemberLink(UUID accountId, UUID personId, boolean active, long version, UUID verifiedBy,
    Instant verifiedAt) {
    public MemberLink {
        if (accountId == null || personId == null || verifiedBy == null || verifiedAt == null || version < 1) {
            throw new IdentityException(IdentityException.Kind.INVALID,
                "A member link requires verified account and person identity.");
        }
    }
    public MemberLink revise(UUID person, boolean enabled, long expected, UUID actor, Instant now) {
        if (version != expected || !personId.equals(person)) {
            throw new IdentityException(IdentityException.Kind.CONFLICT,
                "Refresh member access. Verified identities cannot be reassigned to a different person.");
        }
        return new MemberLink(accountId, personId, enabled, version + 1, actor, now);
    }
}
