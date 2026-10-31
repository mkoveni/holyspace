package org.onechurch.payments.domain.repository;
import org.onechurch.payments.domain.MobileCheckout;

import java.time.Instant;
import java.util.Optional;
public interface MobileCheckoutRepository {
    void insert(MobileCheckout checkout);
    Optional<MobileCheckout> lock(String hash);
    void used(String hash, Instant now);
}
