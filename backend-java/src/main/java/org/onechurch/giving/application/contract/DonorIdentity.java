package org.onechurch.giving.application.contract;
import java.util.Optional;
import java.util.UUID;
/** Verified financial identity, independent of email matching and household relationships. */
public interface DonorIdentity {
    record Donor(UUID id, String name) {}
    Optional<Donor> current();
    UUID requireOwn(UUID actorId, UUID donorId);
}
