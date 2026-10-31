package org.onechurch.giving.domain;
import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
public record Acknowledgement(UUID id, UUID giftId, String donorName, LocalDate issuedOn, UUID createdBy,
    Instant createdAt, List<Line> lines) {
    public record Line(UUID fundId, String fundName, long amountMinor) {}
    public Acknowledgement {
        donorName = GivingRules.text(donorName, 300, "Donor name");
        lines = List.copyOf(lines);
    }
}
