package org.onechurch.giving.domain;
import java.time.Instant;
import java.time.LocalDate;
import java.util.HashSet;
import java.util.List;
import java.util.Set;
import java.util.UUID;
public record Gift(UUID id, UUID donorId, Money money, LocalDate receivedOn, String method, String externalReference,
    List<Allocation> allocations, UUID createdBy, Instant createdAt) {
    public record Allocation(UUID fundId, long amountMinor) {}
    public Gift {
        if (id == null || money == null || receivedOn == null || createdBy == null || createdAt == null) {
            throw GivingException.invalid("Complete the gift details.");
        }
        if (!List.of("CASH", "BANK", "OTHER", "ONLINE").contains(method == null ? "" : method)) {
            throw GivingException
                .invalid("Choose cash, bank or other. Online payments require a verified payment integration.");
        }
        externalReference = externalReference == null || externalReference.isBlank() ? null
            : GivingRules.text(externalReference, 200, "Reference");
        if (allocations == null || allocations.isEmpty() || allocations.size() > 20) {
            throw GivingException.invalid("Allocate the gift to 1–20 funds.");
        }
        if (allocations.stream().anyMatch(java.util.Objects::isNull)) {
            throw GivingException.invalid("Every allocation needs a fund and an amount.");
        }
        allocations = List.copyOf(allocations);
        Set<UUID> seen = new HashSet<>();
        long sum = 0;
        for (Allocation item : allocations) {
            if (item.fundId() == null || item.amountMinor() < 1 || item.amountMinor() > money.amountMinor()
                || !seen.add(item.fundId())) {
                throw GivingException.invalid("Choose each fund once with a positive allocation.");
            }
            sum += item.amountMinor();
        }
        if (sum != money.amountMinor()) {
            throw GivingException.invalid("Fund allocations must equal the full gift amount.");
        }
    }
    public Reversal reverse(UUID requestId, LocalDate date, String reason, UUID actor, Instant now) {
        if (date == null || date.isBefore(receivedOn)) {
            throw GivingException.invalid("A reversal cannot precede the gift's received date.");
        }
        return new Reversal(requestId, id, date, GivingRules.text(reason, 1000, "Reversal reason"), actor, now);
    }
    public record Reversal(UUID id, UUID giftId, LocalDate reversedOn, String reason, UUID createdBy,
        Instant createdAt) {
        public Reversal {
            if (id == null || giftId == null || reversedOn == null || createdBy == null || createdAt == null) {
                throw GivingException.invalid("Complete the reversal details.");
            }
            reason = GivingRules.text(reason, 1000, "Reversal reason");
        }
    }
}
