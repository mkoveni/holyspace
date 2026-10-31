package org.onechurch.childrenministry.messaging.application.contract;
import org.onechurch.childrenministry.messaging.domain.GuardianCommunication;

import java.time.LocalDate;
import java.util.List;
import java.util.Optional;
import java.util.UUID;
public interface GuardianCommunicationReader {
    record Receipt(UUID id, String hash) {}
    record Summary(UUID id, UUID childId, UUID guardianId, UUID programmeId, UUID sessionId, String type,
        LocalDate dueOn, String status, long version) {}
    record Page(List<Summary> communications, long total, long overdue) {}
    Optional<Receipt> receipt(UUID actorId, UUID requestId);
    Optional<GuardianCommunication> find(UUID id);
    Page list(UUID childId, String status, LocalDate on, int limit, int offset);
}
