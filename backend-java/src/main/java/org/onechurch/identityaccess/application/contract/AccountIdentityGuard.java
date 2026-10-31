package org.onechurch.identityaccess.application.contract;
import java.util.UUID;
/** Serialize verified identity changes on the account root before link and People locks. */
public interface AccountIdentityGuard {
    void lock(UUID account);
}
