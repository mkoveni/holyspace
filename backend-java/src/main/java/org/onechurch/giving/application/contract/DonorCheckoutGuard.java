package org.onechurch.giving.application.contract;
import java.util.UUID;
/** Trusted one-time Payments handoff only; the caller must separately verify its bound live session. */
public interface DonorCheckoutGuard {
    boolean lockEligible(UUID account, UUID donor);
}
