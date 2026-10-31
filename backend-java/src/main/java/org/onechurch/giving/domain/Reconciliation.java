package org.onechurch.giving.domain;
import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
public record Reconciliation(UUID id, String reference, long expectedMinor, long actualMinor, String currency,
    LocalDate reconciledOn, String reason, UUID createdBy, Instant createdAt, List<UUID> giftIds) {
    public Reconciliation {
        reference = GivingRules.text(reference, 200, "Bank or cash batch reference");
        new Money(1, currency);
        if (expectedMinor < 1 || expectedMinor > 100_000_000_000_000L || actualMinor < 1
            || actualMinor > 100_000_000_000_000L || giftIds == null || giftIds.isEmpty() || giftIds.size() > 100
            || giftIds.stream().distinct().count() != giftIds.size()) {
            throw GivingException.invalid("Select 1–100 distinct gifts and a positive expected batch amount.");
        }
        reason = reason == null ? "" : reason.trim();
        if (expectedMinor != actualMinor) {
            reason = GivingRules.text(reason, 1000, "Discrepancy explanation");
        }
        if (reason.length() > 1000) {
            throw GivingException.invalid("The explanation must be at most 1000 characters.");
        }
        giftIds = List.copyOf(giftIds);
    }
    public record Review(UUID id, UUID batchId, long version, String outcome, String note, long reversedMinor,
        UUID createdBy, Instant createdAt) {
        public Review {
            note = GivingRules.text(note, 1000, "Review evidence");
            if (!List.of("FOLLOW_UP", "EXPLAINED").contains(outcome)) {
                throw GivingException.invalid("Choose follow-up or explained.");
            }
        }
    }
}
