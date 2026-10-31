package org.onechurch.childrenministry.messaging.application.contract;
import org.onechurch.childrenministry.messaging.domain.VerifiedGuardianEmail;

import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface DeliveryReader extends org.onechurch.communications.application.contract.DeliveryReader {
    List<VerifiedGuardianEmail> emails(UUID guardianId);
    Optional<VerifiedGuardianEmail> verification(UUID id);
}
