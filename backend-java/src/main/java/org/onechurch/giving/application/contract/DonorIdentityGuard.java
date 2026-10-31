package org.onechurch.giving.application.contract;
import java.util.UUID;
/** Command-only financial identity guard; acquire within the transaction before People/fund locks. */
public interface DonorIdentityGuard {
    void lockOwn(UUID actor, UUID donor);
}
