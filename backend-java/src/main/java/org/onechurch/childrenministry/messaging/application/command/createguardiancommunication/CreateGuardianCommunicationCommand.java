package org.onechurch.childrenministry.messaging.application.command.createguardiancommunication;
import java.time.LocalDate;
import java.util.UUID;
public record CreateGuardianCommunicationCommand(UUID actorId, UUID requestId, UUID childId, UUID authorityId,
    UUID programmeId, UUID sessionId, String type, LocalDate dueOn) {}
