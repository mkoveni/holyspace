package org.onechurch.giving.application.contract;
import org.onechurch.giving.domain.Acknowledgement;

import java.time.LocalDate;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface AcknowledgementReader {
    record View(UUID id, UUID giftId, String number, String donorName, LocalDate issuedOn, LocalDate receivedOn,
        long amountMinor, String currency, String method, String reference, boolean voided, LocalDate reversedOn,
        String reversalReason, List<Acknowledgement.Line> lines) {}
    record Status(View acknowledgement) {}
    Optional<View> get(UUID id);
    Optional<View> findForGift(UUID giftId);
}
