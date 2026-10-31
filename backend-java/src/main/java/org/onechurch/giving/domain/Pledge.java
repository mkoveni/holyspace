package org.onechurch.giving.domain;
import java.time.Instant;
import java.time.LocalDate;
import java.time.temporal.ChronoUnit;
import java.util.UUID;
public record Pledge(UUID id, UUID donorId, UUID fundId, Money target, LocalDate startsOn, LocalDate endsOn,
    String status, long version, String cancellationReason, UUID createdBy, Instant createdAt) {
    public Pledge {
        if (id == null || donorId == null || fundId == null || target == null || startsOn == null || endsOn == null
            || endsOn.getYear() > 9999 || endsOn.isBefore(startsOn) || startsOn.getYear() < 1900
            || ChronoUnit.DAYS.between(startsOn, endsOn) > 3660 || version < 0
            || !java.util.List.of("OPEN", "CANCELLED").contains(status)) {
            throw GivingException
                .invalid("A pledge needs a donor, fund, currency and a commitment period of at most ten years.");
        }
    }
    public Pledge cancel(long expected, String reason) {
        GivingRules.version(version, expected);
        requireOpen();
        return new Pledge(id, donorId, fundId, target, startsOn, endsOn, "CANCELLED", version + 1,
            GivingRules.text(reason, 1000, "Cancellation reason"), createdBy, createdAt);
    }
    public Pledge revised() {
        return new Pledge(id, donorId, fundId, target, startsOn, endsOn, status, version + 1, cancellationReason,
            createdBy, createdAt);
    }
    public void requireOpen() {
        if (!status.equals("OPEN")) {
            throw GivingException.conflict("This pledge is cancelled.");
        }
    }
    public void validateAssignment(Gift gift, long amount, long credited, long available) {
        requireOpen();
        new Money(amount, target.currency());
        if (!donorId.equals(gift.donorId()) || !target.currency().equals(gift.money().currency())
            || gift.receivedOn().isBefore(startsOn) || gift.receivedOn().isAfter(endsOn)) {
            throw GivingException.invalid("Choose a gift from this donor, currency and commitment period.");
        }
        if (amount > available || amount > target.amountMinor() - credited) {
            throw GivingException
                .conflict("The assignment exceeds the available gift allocation or remaining pledge target.");
        }
    }
}
