package org.onechurch.payments.domain.repository;
import org.onechurch.payments.domain.Charge;
import org.onechurch.payments.domain.PaymentOrder;
import org.onechurch.payments.domain.Refund;
import org.onechurch.payments.domain.SettlementReview;

import java.time.Instant;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface PaymentRepository {
    Optional<PaymentOrder> find(UUID id);
    Optional<PaymentOrder> lock(UUID id);
    boolean insert(PaymentOrder order);
    void ready(UUID id, String checkoutId, String url, String fields);
    void status(UUID id, String status);
    void review(UUID id, String reason);
    void subscription(UUID id, String status, String encryptedToken, String tokenHash);
    Optional<UUID> lookup(String provider, String mode, String checkoutId, String tokenHash);
    Optional<String> notification(String provider, String mode, String eventId);
    boolean remember(String provider, String mode, String eventId, UUID order, String fingerprint, Instant now);
    Optional<Charge> charge(String provider, String mode, String reference);
    boolean insert(Charge charge);
    List<Charge> charges(UUID order);
    List<Charge> pendingCharges(UUID order);
    List<Charge> matchingCharges(UUID order, UUID charge);
    void posted(UUID charge, UUID gift);
    Optional<Refund> refund(String provider, String mode, String reference);
    boolean insert(Refund refund);
    List<Refund> refunds(UUID order);
    List<Refund> pendingRefunds(UUID order);
    void applied(UUID refund, UUID charge);
    void matchRefund(UUID refund, UUID charge);
    long refunded(UUID charge);
    Optional<SettlementReview> reviewRequest(UUID request);
    Optional<SettlementReview> latestReview(UUID charge);
    void insert(SettlementReview review);
    void audit(UUID order, String action, String note, UUID actor, Instant now);
}
