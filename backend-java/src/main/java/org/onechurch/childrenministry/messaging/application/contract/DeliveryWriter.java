package org.onechurch.childrenministry.messaging.application.contract;
import org.onechurch.childrenministry.messaging.domain.VerifiedGuardianEmail;

import java.time.Instant;
import java.util.UUID;
public interface DeliveryWriter {
    void verify(VerifiedGuardianEmail email);
    void revoke(UUID id, Instant at);
}
