package org.onechurch.childrenministry.messaging.domain.repository;
import org.onechurch.childrenministry.messaging.domain.GuardianCommunication;

import java.util.Optional;
import java.util.UUID;
public interface GuardianCommunicationRepository {
    Optional<GuardianCommunication> lock(UUID id);
    void insert(GuardianCommunication communication, UUID requestId, String requestHash);
    void update(GuardianCommunication communication);
}
