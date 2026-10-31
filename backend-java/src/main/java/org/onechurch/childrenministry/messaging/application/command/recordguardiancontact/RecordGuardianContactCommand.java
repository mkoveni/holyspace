package org.onechurch.childrenministry.messaging.application.command.recordguardiancontact;
import java.util.UUID;
public record RecordGuardianContactCommand(UUID actorId, UUID communicationId, long expectedVersion, String status,
    String reference) {}
