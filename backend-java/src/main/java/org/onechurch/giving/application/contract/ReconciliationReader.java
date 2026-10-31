package org.onechurch.giving.application.contract;
import org.onechurch.giving.domain.Reconciliation;

import java.time.LocalDate;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface ReconciliationReader {
    record Summary(Reconciliation batch, long reversedMinor, long currentNetMinor, long version,
        Reconciliation.Review review, boolean needsReview) {}
    record Page(List<Summary> items, long total) {}
    record Item(UUID giftId, String donorName, LocalDate receivedOn, long amountMinor, boolean reversed,
        long refundedMinor) {}
    record Detail(Summary summary, List<Item> items, List<Reconciliation.Review> reviews) {}
    Page list(int limit, int offset);
    Optional<Detail> detail(UUID id);
}
