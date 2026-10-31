package org.onechurch.giving.application.contract;
import org.onechurch.giving.domain.Fund;
import org.onechurch.giving.domain.Gift;

import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface GivingReader {
    record Filter(LocalDate from, LocalDate to, String currency, UUID fundId, UUID donorId, int limit, int offset) {}
    record GiftSummary(UUID id, UUID donorId, String donorName, long amountMinor, String currency, LocalDate receivedOn,
        String method, String externalReference, boolean reversed, boolean reconciled, long refundedMinor) {}
    record GiftPage(List<GiftSummary> items, long total) {}
    record FundPage(List<Fund> items, long total) {}
    record Detail(Gift gift, String donorName, java.util.Map<UUID, String> fundNames, Gift.Reversal reversal,
        List<History> history, long refundedMinor) {}
    record History(String action, String reason, UUID actorId, Instant occurredAt) {}
    record Total(String currency, String grossMinor, String reversedMinor, String netMinor) {}
    FundPage funds(boolean archived, String search, int limit, int offset);
    GiftPage gifts(Filter filter);
    Optional<Detail> detail(UUID id);
    List<Total> totals(Filter filter);
}
