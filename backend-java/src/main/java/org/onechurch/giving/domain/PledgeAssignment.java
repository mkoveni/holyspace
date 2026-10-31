package org.onechurch.giving.domain;
import java.time.Instant;
import java.util.UUID;
public record PledgeAssignment(UUID id, UUID pledgeId, UUID giftId, UUID fundId, UUID donorId, String currency,
    long amountMinor, String status, long version, String cancellationReason, UUID cancelledBy, Instant cancelledAt,
    UUID createdBy, Instant createdAt) {
    public PledgeAssignment {
        new Money(amountMinor, currency);
        if (id == null || pledgeId == null || giftId == null || fundId == null || donorId == null || createdBy == null
            || createdAt == null || version < 0 || !("ACTIVE".equals(status) || "CANCELLED".equals(status))) {
            throw GivingException.invalid("Invalid pledge assignment evidence.");
        }
        if ("CANCELLED".equals(status)) {
            cancellationReason = GivingRules.text(cancellationReason, 1000, "Cancellation reason");
            if (cancelledBy == null || cancelledAt == null) {
                throw GivingException.invalid("Cancellation evidence needs its actor and time.");
            }
        }
    }
    public PledgeAssignment cancel(long expected, String reason, UUID actor, Instant at) {
        GivingRules.version(version, expected);
        if (!status.equals("ACTIVE")) {
            throw GivingException.conflict("This pledge assignment has already been cancelled.");
        }
        return new PledgeAssignment(id, pledgeId, giftId, fundId, donorId, currency, amountMinor, "CANCELLED",
            version + 1, GivingRules.text(reason, 1000, "Cancellation reason"), actor, at, createdBy, createdAt);
    }
}
