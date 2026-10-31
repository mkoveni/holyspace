package org.onechurch.payments.application.contract;
import java.time.Instant;
import java.time.LocalDate;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface DonorPaymentReader {
    record Order(UUID id, UUID fundId, long amountMinor, String provider, String mode, boolean recurring, String status,
        String subscriptionStatus, long version, Instant createdAt) {}
    record Confirmation(String subject, String html) {}
    record Charge(UUID id, long amountMinor, long refundedMinor, LocalDate receivedOn, String givingStatus,
        String confirmationStatus, boolean captured, Confirmation confirmation) {}
    record Refund(long amountMinor, LocalDate refundedOn, boolean applied) {}
    record Detail(Order order, List<Charge> charges, List<Refund> refunds) {}
    record Page(List<Order> items, int total) {}
    Page forDonor(UUID donorId, int limit, int offset);
    Optional<Detail> ownDetail(UUID donorId, UUID orderId);
}
