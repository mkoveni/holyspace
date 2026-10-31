package org.onechurch.communications.application.contract;
import java.time.Instant;
import java.util.UUID;
/** The originating context revalidates its own eligibility and delegated approval. */
public interface DeliveryEligibility {
    String sourceContext();
    void authorize(DeliverySnapshot delivery, UUID actor, String operation);
    boolean eligible(DeliverySnapshot delivery, Instant at);
}
