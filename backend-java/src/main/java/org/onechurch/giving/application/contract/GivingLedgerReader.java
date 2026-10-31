package org.onechurch.giving.application.contract;
import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.UUID;
public interface GivingLedgerReader {
    record Line(UUID giftId, String kind, LocalDate date, UUID donorId, String donorName, UUID fundId, String fundName,
        String currency, long amountMinor, String method, String reference, String reason) {}
    record Report(UUID donorId, String donorName, LocalDate from, LocalDate to, Instant generatedAt, List<Line> lines,
        List<GivingReader.Total> totals) {}
    List<Line> lines(GivingReader.Filter filter, int maximum);
}
